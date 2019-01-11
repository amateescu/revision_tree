<?php

namespace Drupal\revision_tree\Context;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Interface for the entity context manager service.
 *
 * Provides an API to find the relevant contexts for a given entity type.
 */
interface RevisionNegotiationContextDiscoveryInterface {

  /**
   * Retrieve the list of contexts for a given entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type object.
   *
   * @return array
   *   An array of context weights, keyed by the context id.
   */
  public function getEntityContextDefinitions(EntityTypeInterface $entityType);

}
