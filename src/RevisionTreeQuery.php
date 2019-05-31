<?php

namespace Drupal\revision_tree;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Baseline implementation of `RevisionTreeQueryInterface`.
 *
 * Avoids any performance optimizations. Heavy use of sub-queries.
 */
class RevisionTreeQuery implements RevisionTreeQueryInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * RevisionTreeQuery constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextualTree(ContentEntityTypeInterface $entityType, array $contexts) {
    $contextualFields = [
      'workspace' => [
        'weight' => 10,
        'context' => '@revision_tree.workspace_context:hierarchy',
      ],
    ];

    $parent_field = $entityType->getRevisionMetadataKey('revision_parent');
    $merge_parent_field = $entityType->getRevisionMetadataKey('revision_merge_parent');
    $query = $this->database->select($entityType->getRevisionTable(), 'base');
    $query->addField('base', $entityType->getKey('id'), 'entity_id');
    $query->addField('base', $entityType->getKey('revision'), 'revision_id');
    $query->addField('base', $parent_field, 'parent');
    $query->addField('base', $merge_parent_field, 'merge_parent');

    if ($contexts) {
      $pruningCondition = new Condition('AND');

      foreach ($contexts as $contextField => $values) {
        if (!array_key_exists($contextField, $contextualFields)) {
          continue;
        }

        $contextCondition = new Condition('OR');
        $contextCondition->isNull($contextField);
        $values = array_filter(is_array($values) ? $values : [$values]);
        if (count($values) > 0) {
          $contextCondition->condition($contextField, $values, 'IN');
        }
        $pruningCondition->condition($contextCondition);
      }
      if ($pruningCondition->count()) {
        $query->condition($pruningCondition);
      }
    }

    list($expression, $arguments)= $this->buildMatchingScoreExpression($entityType, $contexts);
    $query->addExpression($expression, 'score', $arguments);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextualLeaves(ContentEntityTypeInterface $entityType, array $contexts) {
    $parents = $this->getContextualTree($entityType, $contexts);
    $children = $this->getContextualTree($entityType, $contexts);

    $idField = $entityType->getKey('id');
    $revisionField = $entityType->getKey('revision');

    $parents->leftJoin($children, 'children', "base.$idField = children.entity_id AND (base.$revisionField = children.parent OR base.$revisionField = children.merge_parent)");
    $parents->isNull('children.entity_id');
    return $parents;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveLeaves(ContentEntityTypeInterface $entityType, array $contexts) {
    $query = $this->database->select($entityType->getRevisionTable(), 'base');
    $query->addField('base', $entityType->getKey('id'), 'entity_id');
    $query->addField('base', $entityType->getKey('revision'), 'revision_id');

    $revisionField = $entityType->getKey('revision');

    $query->join($this->getContextualLeaves($entityType, $contexts), 'l', "base.$revisionField = l.revision_id");
    $query->leftJoin($this->getContextualLeaves($entityType, $contexts), 'r', "l.entity_id = r.entity_id AND (l.score < r.score OR (l.score = r.score AND l.revision_id < r.revision_id))");
    $query->isNull('r.entity_id');
    return $query;
  }

  /**
   * Build an SQL expression that will result in a context matching score.
   *
   * @param $field
   *   The database field name.
   * @param $weight
   *   The weight of this context.
   * @param array $values
   *   The list of context values, in descending order of relevance.
   * @param int $level
   *   Private: The current recursion level. Do use in initial call.
   *
   * @return array
   *   A tuple consisting of the SQL expression and the arguments array.
   */
  protected function buildContextFallbackExpression($field, $weight, array $values, $level = 0) {
    if (count($values) === 0) {
      return ['0', []];
    }

    $value = array_shift($values);

    $rankedWeight = ($weight - $level * 0.01);
    list($else, $arguments) = static::buildContextFallbackExpression($field, $weight, $values, $level + 1);

    $condition = is_null($value) ? "base.{$field} IS NULL" : "base.{$field} = :{$field}__{$level}__value";
    if (!is_null($value)) {
      $arguments[":{$field}__{$level}__value"] = $value;
    }

    return ["IF({$condition}, {$rankedWeight}, $else)", $arguments];
  }

  /**
   * Build matching expression.
   *
   * Used to quantify the distance to the current context values.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entityType
   *   The entity type to be queried.
   * @param array $contexts
   *   The list of context values, in descending order of relevance.
   *
   * @return array
   *   Tuple of the SQL expression and the arguments array.
   */
  protected function buildMatchingScoreExpression(ContentEntityTypeInterface $entityType, array $contexts) {
    $allContextDefinitions = [
      'workspace' => [
        'weight' => 10,
        'context' => '@revision_tree.workspace_context:hierarchy',
      ],
    ];

    // The default expression just returns '0'.
    $expressions[] = [
      '0',
      [],
    ];

    foreach ($contexts as $contextField => $value) {
      if (array_key_exists($contextField, $allContextDefinitions)) {
        $expressions[] = $this->buildContextFallbackExpression(
          $contextField,
          NestedArray::getValue($allContextDefinitions, [$contextField, 'weight']) ?: 0,
          is_array($value) ? $value : [$value]
        );
      }
    }

    $expression = implode(' + ', array_map(function($expr) {
      return $expr[0];
    }, $expressions));

    $arguments = array_reduce(array_map(function ($expr) {
      return $expr[1];
    }, $expressions), 'array_merge', []);

    return [$expression, $arguments];
  }
}
