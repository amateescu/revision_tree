<?php

namespace Drupal\revision_tree\EntityQuery;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\Sql\Query as BaseQuery;
use Drupal\revision_tree\Context\RevisionNegotiationContextDiscoveryInterface;

/**
 * Alters entity queries to use a workspace revision instead of the default one.
 */
class Query extends BaseQuery {

  /**
   * @var \Drupal\revision_tree\Context\RevisionNegotiationContextDiscoveryInterface
   */
  protected $negotiationContextDiscovery;


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

  public function __construct(
    EntityTypeInterface $entity_type,
    string $conjunction,
    Connection $connection,
    array $namespaces,
    RevisionNegotiationContextDiscoveryInterface $negotiationContextDiscovery
  ) {
    parent::__construct($entity_type, $conjunction, $connection, $namespaces);
    $this->negotiationContextDiscovery = $negotiationContextDiscovery;
  }


  /**
   * {@inheritdoc}
   */
  public function prepare() {
    parent::prepare();
    $allContextDefinitions = $this->negotiationContextDiscovery
      ->getEntityContextDefinitions($this->entityType);
    foreach ($this->contextSorts as $index => $contextSort) {
      list($contexts, $direction, $lancode) = $contextSort;
      $expressions = ['0'];
      foreach ($contexts as $context => $value) {
        if (array_key_exists($context, $allContextDefinitions)) {
          $definition = $allContextDefinitions[$context];
          // If the weight is negative, we are looking for a non-match.
          $operator = $definition['weight'] > 0 ? '=' : '!=';
          $field = $this->getSqlField($definition['field'], $lancode);
          $expressions[] = "IF({$field} {$operator} '{$value}', 1, 0) * {$definition['weight']}";
        }
      }
      $this->contextSortExpressions[$index] = [
        implode(' + ', $expressions),
        $direction,
      ];
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function finish() {
    foreach ($this->contextSortExpressions as $index => list($expression, $direction)) {
      $alias = 'revision_context_match_' . $index;
      $this->sqlQuery->addExpression($expression, $alias);
      $this->sqlQuery->orderBy($alias, $direction);
    }
    return parent::finish();
  }

}
