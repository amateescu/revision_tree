<?php

namespace Drupal\revision_tree\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepository as CoreEntityRepository;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Forked version of the entity repository to implement revision negotiation.
 */
class EntityRepository extends CoreEntityRepository {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($entity_type_manager, $language_manager);
  }

  public function getActive(EntityInterface $entity, array $contexts) {
    if ($entity->getEntityType()->isRevisionable()) {
      $result = $this->getActiveMultiple($entity->getEntityType(), [$entity->id()], $contexts);
      if ($result) {
        return reset($result);
      }
    }
    return $entity;
  }

  public function getActiveMultiple(EntityTypeInterface $entityType, array $entityIds, array $contexts) {
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entityType->id());
    /** @var \Drupal\revision_tree\EntityQuery\Query $query */
    $query = $storage->getQuery();

    $query->activeRevisions($contexts);
    $query->condition($entityType->getKey('id'), $entityIds);

    $result = $query->execute();
    return $storage->loadMultipleRevisions(array_keys($result));
  }

}
