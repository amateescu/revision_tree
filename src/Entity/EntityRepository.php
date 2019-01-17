<?php

namespace Drupal\revision_tree\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepository as CoreEntityRepository;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;

/**
 * Forked version of the entity repository to implement revision negotiation.
 */
class EntityRepository extends CoreEntityRepository {

  /**
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    ContextRepositoryInterface $contextRepository
  ) {
    parent::__construct($entity_type_manager, $language_manager);
    $this->contextRepository = $contextRepository;
  }

  public function getActive(EntityInterface $entity, array $contexts = []) {
    if ($entity->getEntityType()->isRevisionable()) {
      $result = $this->getActiveMultiple($entity->getEntityType(), [$entity->id()], $contexts);
      if ($result) {
        return reset($result);
      }
    }
    return $entity;
  }

  public function getActiveMultiple(EntityTypeInterface $entityType, array $entityIds, array $contexts = []) {
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entityType->id());
    /** @var \Drupal\revision_tree\EntityQuery\Query $query */
    $query = $storage->getQuery();

    $contextualFields = $entityType->get('contextual_fields');

    $fieldContexts = array_map(function ($field) {
      return $field['context'];
    }, $contextualFields);

    $runtimeContexts = $this->contextRepository->getRuntimeContexts(array_values($fieldContexts));

    $contextValues = array_map(function (ContextInterface $context) {
      return $context->getContextValue();
    }, $runtimeContexts);

    $entityContexts = array_merge(array_map(function ($field) use ($contextValues) {
      return $contextValues[$field['context']];
    }, $contextualFields), $contexts);

    $query->activeRevisions(array_filter($entityContexts));
    $query->condition($entityType->getKey('id'), $entityIds);

    $result = $query->execute();
    return $storage->loadMultipleRevisions(array_keys($result));
  }

}
