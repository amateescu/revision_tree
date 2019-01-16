<?php

namespace Drupal\revision_tree\EntityQuery;

use Drupal\Component\Utility\SortArray;
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
  protected $sortContexts = [];

  /**
   * The sort direction.
   *
   * @var string
   */
  protected $sortDirection = 'DESC';

  /**
   * @var bool
   */
  protected $activeRevisions = false;

  /**
   * @var array
   */
  private $contextSortExpression = '0';

  /**
   * Retrieve the active revisions for each result entity.
   *
   * @param array $contexts
   *   The list of target context values keyed by field name.
   *
   */
  public function activeRevisions(array $contexts) {
    // TODO: Narrow down to leaves based on the tree field.
    $this->allRevisions();
    $this->activeRevisions = TRUE;
    $this->orderByContextMatching($contexts);
  }

  /**
   * Order entity query results by context matching.
   *
   * @param array $contexts
   * @param string $direction
   * @param null $langcode
   */
  public function orderByContextMatching(array $contexts, $direction = 'DESC') {
    $this->sortContexts = $contexts;
    $this->sortDirection = $direction;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare() {
    parent::prepare();
    // Retrieve entity contexts from the entity type definition.
    $allContextDefinitions = $this->entityType->get('contextual_fields');

    // The default expression just returns '0'.
    $expressions[] = [
      '0',
      [],
    ];

    foreach ($this->sortContexts as $context => $value) {
      if (array_key_exists($context, $allContextDefinitions)) {
        $weight = $allContextDefinitions[$context];
        // If the weight is negative, we are looking for a non-match.
        $operator = $weight > 0 ? '=' : '!=';

        $langcode = NULL;
        if (is_array($value) && array_key_exists('langcode', $value) && array_key_exists('value', $value)) {
          $langcode = $value['langcode'];
          $value = $value['value'];
        }

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

    $this->contextSortExpression = $expressions;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function finish() {
    if (!$this->contextSortExpression) {
      return parent::finish();
    }

    $alias = 'revision_context_match';

    $expression = implode(' + ', array_map(function($expr) {
      return $expr[0];
    }, $this->contextSortExpression));

    $arguments = array_reduce(array_map(function ($expr) {
      return $expr[1];
    }, $this->contextSortExpression), 'array_merge', []);

    $this->sqlQuery->addExpression($expression, $alias, $arguments);

    if ($this->activeRevisions) {
      // If this is an active revisions query, build the set of weighted
      // revision ids.
      $idField = $this->getSqlField($this->entityType->getKey('id'), NULL);
      $revisionField = $this->getSqlField($this->entityType->getKey('revision'), NULL);
      $this->sqlQuery->groupBy($idField);

      // Build an expression that results in a json-array with tuples of
      // revision id's and their matching score.
      // This array is parsed in `execute()` and the highest ranked revision id
      // will be returned.
      //
      // Example:
      // [
      //   [ '1', 0 ],
      //   [ '3', 1.1 ],
      //   [ '8', 0.6 ]
      // ]
      $concatExpr = "CONCAT('[', GROUP_CONCAT(CONCAT('[\"', REPLACE($revisionField, '\"', '\\\"'), '\",', $expression, ']')), ']')";
      $this->sqlQuery->addExpression($concatExpr, $this->entityType->getKey('revision'));
    }
    else {
      // If this is not an active revisions query, just sort the results by
      // matching score.
      $this->sqlQuery->orderBy($alias, $this->sortDirection);
    }

    return parent::finish();
  }

  public function execute() {
    // If this is not an activeRevisions query, just return the result.
    if (!$this->activeRevisions) {
      return parent::execute();
    }

    // If we are looking for active revisions, a weighted json array is returned.
    // Find the revision id with the highest matching score.
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
