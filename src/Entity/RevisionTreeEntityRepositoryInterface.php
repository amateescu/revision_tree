<?php

namespace Drupal\revision_tree\Entity;

use Drupal\Core\Entity\EntityRepositoryInterface;

/**
 * Interface RevisionTreeEntityRepositoryInterface
 *
 * Extended entity repository interface that handles revision trees.
 */
interface RevisionTreeEntityRepositoryInterface extends EntityRepositoryInterface {

  /**
   * Get the active revision of an entity.
   *
   * Retrieves the revision of a given entity that is considered "active" or
   * "save to edit".
   *
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $entityId
   *   The target entity id to search active revisions for.
   * @param array $contexts
   *   An array of context values keyed by field name. Arrays can be passed
   *   as values to trigger context fallback behavior:
   *
   *     ['my-workspace', 'stage', 'live']
   *
   *   This example would prefer revisions in the 'my-workspace' workspace, but
   *   then fall back to stage and subsequently live.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The "active" revision of the target entity.
   */
  public function getActive($entityTypeId, $entityId, array $contexts = []);

  /**
   * Get the active revisions for a set of  entities.
   *
   * Retrieves the revision of for all entities that is considered "active" or
   * "save to edit".
   *
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string[] $entityIds
   *   The target entity id to search active revisions for.
   * @param array $contexts
   *   An array of context values keyed by field name. Arrays can be passed
   *   as values to trigger context fallback behavior:
   *
   *     ['my-workspace', 'stage', 'live']
   *
   *   This example would prefer revisions in the 'my-workspace' workspace, but
   *   then fall back to stage and subsequently live.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The "active" revision of the target entity.
   */
  public function getActiveMultiple($entityTypeId, array $entityIds, array $contexts = []);

}
