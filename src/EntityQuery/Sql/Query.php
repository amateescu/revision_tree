<?php

namespace Drupal\revision_tree\EntityQuery\Sql;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Query\Sql\Query as BaseQuery;

/**
 * Extended entity query that allows to sort by contexts and query active revisions.
 */
class Query extends BaseQuery {

  /**
   * The list of sort context values, keyed by field name.
   *
   * @var array
   */
  protected $matchingContexts = [];

  /**
   * @var bool
   */
  protected $activeRevisions = false;


  /**
   * Retrieve the active revisions for each result entity.
   *
   * @param array $contexts
   *   The list of target context values keyed by field name.
   *
   * @return \Drupal\revision_tree\EntityQuery\Sql\Query
   *   The current query object.
   */
  public function activeRevisions(array $contexts) {
    $this->allRevisions();
    $this->activeRevisions = TRUE;
    $this->matchingContexts = $contexts;
    return $this;
  }

  /**
   *
   */
  protected function buildContextFallbackExpression($field, $weight, array $values, $level = 0) {
    if (count($values) === 0) {
      return ['0', []];
    }

    $value = array_shift($values);

    $rankedWeight = ($weight - $level * 0.01);
    list($else, $arguments) = static::buildContextFallbackExpression($field, $weight, $values, $level + 1);

    $condition = is_null($value) ? "base_table.{$field} IS NULL" : "base_table.{$field} = :{$field}__{$level}__value";
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
   * @param $table
   *   The table name.
   *
   * @return array
   *   Tuple of the SQL expression and the arguments array.
   */
  protected function buildMatchingScoreExpression($table) {
    $allContextDefinitions = $this->entityType->get('contextual_fields') ?: [];

    // The default expression just returns '0'.
    $expressions[] = [
      '0',
      [],
    ];

    foreach ($this->matchingContexts as $contextField => $value) {
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

  /**
   * {@inheritdoc}
   */
  protected function finish() {
    if (!$this->activeRevisions) {
      return parent::finish();
    }

    $idField = $this->entityType->getKey('id');
    $revisionField = $this->entityType->getKey('revision');
    $baseTable = $this->entityType->getRevisionTable();

    // Create a temporary table with all leaves of the revision tree and their
    // matching score that will tell us which revision is the most appropriate
    // one.
    /** @var \Drupal\Core\Database\Query\Select $rankedLeavesQuery */
    $rankedLeavesQuery = $this->connection->select($baseTable, 'base_table');
    $rankedLeavesQuery->fields('base_table', [$idField, $revisionField]);

    // TODO: Properly pull them out of query tables.
    $parentField = 'revision_parent__target_id';
    $mergeParentField = 'revision_parent__merge_target_id';

    $rankedLeavesQuery->leftJoin($baseTable, 'children', "base_table.$revisionField = children.$parentField OR base_table.$revisionField = children.$mergeParentField");
    // We consider the root as a leaf, since we might have to branch from there.
    $rankedLeavesQuery->condition($rankedLeavesQuery->orConditionGroup()
      ->isNull("children.$revisionField")
      ->isNull("base_table.$parentField"));

    list($expression, $arguments) = $this->buildMatchingScoreExpression('base_table');
    $rankedLeavesQuery->addExpression($expression, 'score', $arguments);

    // Join the ranking field into the query.
    $this->sqlQuery->join($rankedLeavesQuery, 'l', "base_table.$revisionField = l.$revisionField");
    // Join the ranking a second time, to find the revisions that have no higher
    // scored sibling.
    $this->sqlQuery->leftJoin($rankedLeavesQuery, 'r', "l.$idField = r.$idField AND (l.score < r.score OR (l.score = r.score AND l.$revisionField < r.$revisionField))");
    $this->sqlQuery->isNull("r.score");

    return parent::finish();
  }

}
