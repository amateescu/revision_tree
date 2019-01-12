<?php

namespace Drupal\revision_tree\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepository as CoreEntityRepository;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\revision_tree\Context\RevisionNegotiationContextDiscoveryInterface;

/**
 * Forked version of the entity repository to implement revision negotiation.
 */
class EntityRepository extends CoreEntityRepository {

  protected $revisionNegotiationContextDiscovery;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    RevisionNegotiationContextDiscoveryInterface $revisionNegotiationContextDiscovery
  ) {
    parent::__construct($entity_type_manager, $language_manager);
    $this->revisionNegotiationContextDiscovery = $revisionNegotiationContextDiscovery;
  }

  public function getActive(EntityInterface $entity, array $contexts) {
    // If an entity is not revisionable, there is only one active revision.
    if (!$entity->getEntityType()->isRevisionable()) {
      return $entity;
    }

    $entityType = $entity->getEntityType();

    /** @var \Drupal\revision_tree\EntityQuery\Query $query */
    $query = $this->entityTypeManager
      ->getStorage($entity->getEntityTypeId())
      ->getQuery();

    $query->allRevisions();
    $query->condition($entityType->getKey('id'), $entity->id());
    $query->orderByContextMatching($contexts);

    $result = $query->execute();
    return $this->loadRevision($entity, key($result));
  }

}
