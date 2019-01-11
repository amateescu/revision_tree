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

    $query = $this->entityTypeManager
      ->getStorage($entity->getEntityTypeId())
      ->getQuery();

    $query->condition($entityType->getKey('id'), $entity->id());

    // TODO: Replace with "allActiveRevisions".
    $query->allRevisions();

    $allContextDefinitions = $this
      ->revisionNegotiationContextDiscovery
      ->getEntityContextDefinitions($entity->getEntityType());

    $expressions = ['0'];
    foreach ($contexts as $context => $value) {
      if (array_key_exists($context, $allContextDefinitions)) {
        $definition = $allContextDefinitions[$context];
        // If the weight is negative, we are looking for a non-match.
        $operator = $definition['weight'] > 0 ? '=' : '!=';
        $expressions[] = "IF(base_table.{$definition['field']} {$operator} '{$value}', 1, 0) * {$definition['weight']}";
      }
    }

    $query->addTag('revision_negotiation_query');
    $query->addMetaData('revision_negotiation_expression', implode(' + ', $expressions));

    $result = $query->execute();
    return $this->loadRevision($entity, key($result));
  }

}
