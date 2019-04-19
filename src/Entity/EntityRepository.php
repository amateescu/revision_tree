<?php

namespace Drupal\revision_tree\Entity;

use Drupal\Core\Entity\EntityRepository as CoreEntityRepository;
use Drupal\Core\Plugin\Context\ContextInterface;

/**
 * Forked version of the entity repository to implement revision negotiation.
 */
class EntityRepository extends CoreEntityRepository {

  /**
   * {@inheritdoc}
   */
  public function getActiveMultiple($entity_type_id, array $entity_ids, array $contexts = NULL) {
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entityType = $this->entityTypeManager->getDefinition($entity_type_id);

    if (!$entityType->isRevisionable()) {
      return parent::getActiveMultiple($entity_type_id, $entity_ids, $contexts);
    }

    $contextualFields = $entityType->get('contextual_fields') ?: [];

    $fieldContexts = array_map(function ($field) {
      return $field['context'];
    }, $contextualFields);

    $runtimeContexts = $this->contextRepository->getRuntimeContexts(array_values($fieldContexts));

    $contextValues = array_map(function (ContextInterface $context) {
      return $context->getContextValue();
    }, $runtimeContexts);

    $entityContexts = array_map(function ($field) use ($contextValues) {
      return $contextValues[$field['context']];
    }, $contextualFields);

    // Apply overrides.
    if ($contexts) {
      foreach ($contexts as $key => $value) {
        if (array_key_exists($key, $fieldContexts)) {
          $entityContexts[$key] = $value;
        }
      }
    }
    /** @var \Drupal\revision_tree\EntityQuery\Sql\Query $query */
    $query = $storage->getQuery();

    if ($entityContexts) {
      $query->activeRevisions(array_filter($entityContexts));
    }

    $query->condition($entityType->getKey('id'), $entity_ids, 'IN');
    $result = $query->execute();
    return $storage->loadMultipleRevisions(array_keys($result));
  }

}
