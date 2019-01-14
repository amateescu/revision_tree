<?php

namespace Drupal\revision_tree\EntityQuery;

use Drupal\Core\Entity\Query\Sql\Query as BaseQuery;

/**
 * Alters entity queries to use a workspace revision instead of the default one.
 */
class Query extends BaseQuery {

  /**
   * @var array
   */
  protected $contextSorts = [];

  /**
   * @var array
   */
  private $contextSortExpressions = [];

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
    $this->contextSorts[] = [
      $contexts,
      $direction,
      $langcode,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepare() {
    parent::prepare();
    $allContextDefinitions = $this->entityType->get('entity_contexts');

    foreach ($this->contextSorts as $index => $contextSort) {
      list($contexts, $direction, $lancode) = $contextSort;
      $expressions[] = [
        '0',
        [],
      ];
      foreach ($contexts as $context => $value) {
        if (array_key_exists($context, $allContextDefinitions)) {
          $weight = $allContextDefinitions[$context];
          // If the weight is negative, we are looking for a non-match.
          $operator = $weight > 0 ? '=' : '!=';
          $sqlField = $this->getSqlField($context, $lancode);
          $field = $context;
          $expressions[] = [
            "IF({$sqlField} {$operator} :{$field}__value, 1, 0) * :{$field}__weight",
            [
              ":{$field}__value" => $value,
              ":{$field}__weight" => $weight,
            ],
          ];
        }
      }
      $this->contextSortExpressions[$index] = [
        $expressions,
        $direction,
      ];
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function finish() {
    foreach ($this->contextSortExpressions as $index => list($expressions, $direction)) {
      $alias = 'revision_context_match_' . $index;

      $expression = implode(' + ', array_map(function($expr) {
        return $expr[0];
      }, $expressions));

      $arguments = array_reduce(array_map(function ($expr) {
        return $expr[1];
      }, $expressions), 'array_merge', []);

      $this->sqlQuery->addExpression($expression, $alias, $arguments);
      $this->sqlQuery->orderBy($alias, $direction);
    }
    return parent::finish();
  }

}
