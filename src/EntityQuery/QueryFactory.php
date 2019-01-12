<?php

namespace Drupal\revision_tree\EntityQuery;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\Sql\QueryFactory as BaseQueryFactory;
use Drupal\revision_tree\Context\RevisionNegotiationContextDiscoveryInterface;

/**
 * Workspaces-specific entity query implementation.
 */
class QueryFactory extends BaseQueryFactory {

  protected $negotiationContextDiscover;

  /**
   * Constructs a QueryFactory object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection used by the entity query.
   * @param \Drupal\revision_tree\Context\RevisionNegotiationContextDiscoveryInterface $negotiationContextDiscovery
   *   The negotiation context discovery to use.
   */
  public function __construct(Connection $connection, RevisionNegotiationContextDiscoveryInterface $negotiationContextDiscovery) {
    parent::__construct($connection);
    $this->negotiationContextDiscover = $negotiationContextDiscovery;
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    $class = QueryBase::getClass($this->namespaces, 'Query');
    return new $class($entity_type, $conjunction, $this->connection, $this->namespaces, $this->negotiationContextDiscover);
  }

}
