<?php

namespace Drupal\revision_tree\EntityQuery;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\Query\Sql\Query as BaseQuery;

/**
 * Alters entity queries to use a workspace revision instead of the default one.
 */
class Query extends BaseQuery {

  /**
   * @var array
   */
  protected $contextSort = [];

  /**
   * @var bool
   */
  protected $activeRevisions = false;

  /**
   * @var string
   */
  protected $langcode;

  /**
   * @var array
   */
  private $contextSortExpression = '0';

  /**
   * Order the results by matching context fields.
   *
   * @param array $contexts
   *   An array of context values keyed by context id.
   * @param string $direction
   *   Either 'ASC' or 'DESC'.
   * @param string | null $langcode
   */
  public function orderByContextMatching(array $contexts, $direction = 'DESC', $langcode = NULL) {
    $this->contextSort = [
      $contexts,
      $direction,
      $langcode,
    ];
  }

  public function activeRevisions(array $contexts, $langcode = NULL) {
    // TODO: Narrow down to leaves based on the tree field.
    $this->allRevisions();
    $this->activeRevisions = TRUE;
    $this->langcode = $langcode;
    $this->orderByContextMatching($contexts, 'DESC', $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function prepare() {
    parent::prepare();
    $allContextDefinitions = $this->entityType->get('entity_contexts');

    list($contexts, $direction, $langcode) = $this->contextSort;
    $expressions[] = [
      '0',
      [],
    ];

    foreach ($contexts as $context => $value) {
      if (array_key_exists($context, $allContextDefinitions)) {
        $weight = $allContextDefinitions[$context];
        // If the weight is negative, we are looking for a non-match.
        $operator = $weight > 0 ? '=' : '!=';
        $sqlField = $this->getSqlField($context, $langcode);
        $field = $context;
        if (is_array($value)) {
          foreach ($value as $index => $val) {
            $expressions[] = [
              "IF({$sqlField} {$operator} :{$field}__value_{$index}, 1, 0) * :{$field}__weight_{$index}",
              [
                ":{$field}__value_{$index}" => $val,
                ":{$field}__weight_{$index}" => $weight + (0.01 * $weight/abs($weight)),
              ],
            ];
          }
        }
        else {
          $expressions[] = [
            "IF({$sqlField} {$operator} :{$field}__value, 1, 0) * :{$field}__weight",
            [
              ":{$field}__value" => $value,
              ":{$field}__weight" => $weight,
            ],
          ];
        }
      }
    }

    $this->contextSortExpression = [
      $expressions,
      $direction,
    ];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function finish() {
    if (!$this->contextSortExpression) {
      return parent::finish();
    }

    list($expressions, $direction) = $this->contextSortExpression;
    $alias = 'revision_context_match';

    $expression = implode(' + ', array_map(function($expr) {
      return $expr[0];
    }, $expressions));

    $arguments = array_reduce(array_map(function ($expr) {
      return $expr[1];
    }, $expressions), 'array_merge', []);

    $this->sqlQuery->addExpression($expression, $alias, $arguments);
    $this->sqlQuery->orderBy($alias, $direction);

    if ($this->activeRevisions) {
      $idField = $this->getSqlField($this->entityType->getKey('id'), $this->langcode);
      $revisionField = $this->getSqlField($this->entityType->getKey('revision'), $this->langcode);
      $this->sqlQuery->groupBy($idField);
      // Build an expression that contains all revision ids with their fitness values.
      $this->sqlQuery->addExpression("CONCAT('[', GROUP_CONCAT(CONCAT('[\"', REPLACE($revisionField, '\"', '\\\"'), '\",', $expression, ']')), ']')", $this->entityType->getKey('revision'));
    }

    return parent::finish();
  }

  public function execute() {
    if (!$this->activeRevisions) {
      return parent::execute();
    }
    // If we are looking for active revisions, a weighted json array is returned.
    // Find the highest matching revision id.
    $result = parent::execute();
    $processed = [];
    foreach ($result as $encoded => $id) {
      $ranked = json_decode($encoded);
      usort($ranked, function ($a, $b) {
        return SortArray::sortByKeyInt($b, $a, 1);
      });
      $processed[$ranked[0][0]] = $id;
    }
    return $processed;
  }

}
