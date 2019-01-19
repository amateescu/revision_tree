<?php

namespace Drupal\revision_tree\EntityQuery\Sql;

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
   */
  public function activeRevisions(array $contexts) {
    $this->allRevisions();
    $this->activeRevisions = TRUE;
    $this->matchingContexts = $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare() {
    parent::prepare();
    // Retrieve entity contexts from the entity type definition.
    return $this;
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
    $allContextDefinitions = $this->entityType->get('contextual_fields');
    // The default expression just returns '0'.
    $expressions[] = [
      '0',
      [],
    ];

    foreach ($this->matchingContexts as $context => $value) {
      if (array_key_exists($context, $allContextDefinitions)) {
        $weight = $allContextDefinitions[$context]['weight'];

        // If the weight is negative, we are looking for a non-match.
        $operator = $weight > 0 ? '=' : '!=';

        $sqlField = "$table.$context";
        $field = $context;
        if (is_array($value)) {
          foreach ($value as $index => $val) {
            $expressions[] = [
              "IF({$sqlField} IS NOT NULL AND {$sqlField} {$operator} :{$field}__value_{$index}, 1, 0) * :{$field}__weight_{$index}",
              [
                ":{$field}__value_{$index}" => $val,
                ":{$field}__weight_{$index}" => $weight + (0.01 * $weight/abs($weight)),
              ],
            ];
          }
        }
        else {
          $expressions[] = [
            "IF({$sqlField} IS NOT NULL AND {$sqlField} {$operator} :{$field}__value, 1, 0) * :{$field}__weight",
            [
              ":{$field}__value" => $value,
              ":{$field}__weight" => $weight,
            ],
          ];
        }
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

    // TODO: Properly pull them out of query tables.
    $parentField = 'revision_parent__target_id';
    $mergeParentSqlField = 'revision_parent__merge_target_id';

    // Create a temporary table with all leaves of the revision tree and their
    // matching score that will tell us which revision is the most appropriate
    // one.
    /** @var \Drupal\Core\Database\Query\Select $rankedLeavesQuery */
    $rankedLeavesQuery = $this->connection->select($baseTable, 'base_table');
    $rankedLeavesQuery->fields('base_table', [$idField, $revisionField]);
    $rankedLeavesQuery->leftJoin($baseTable, 'children', "base_table.$revisionField = children.$parentField AND base_table.$revisionField = children.$mergeParentSqlField");
    $rankedLeavesQuery->isNull("children.$revisionField");

    list($expression, $arguments) = $this->buildMatchingScoreExpression('base_table');
    $rankedLeavesQuery->addExpression($expression, 'score', $arguments);
    $rankedLeaves = $this->connection->queryTemporary(
      (string) $rankedLeavesQuery,
      $rankedLeavesQuery->getArguments()
    );

    // Join the ranking field into the query.
    $this->sqlQuery->join($rankedLeaves, 'l', "base_table.$revisionField = l.$revisionField");
    // Join the ranking a second time, to find the revisions that have no higher
    // scored sibling.
    $this->sqlQuery->leftJoin($rankedLeaves, 'r', "l.$idField = r.$idField AND l.score < r.score");
    $this->sqlQuery->isNull("r.score");

    return parent::finish();
  }

}
