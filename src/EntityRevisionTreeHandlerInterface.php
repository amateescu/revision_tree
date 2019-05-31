<?php

namespace Drupal\revision_tree;

use Drupal\Core\Entity\RevisionableInterface;

/**
 * Defines an interface for entity revision tree handlers.
 */
interface EntityRevisionTreeHandlerInterface {

  /**
   * Gets the parent revisions ID.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revisionable entity object.
   *
   * @return int|string
   *   The parent revision ID.
   */
  public function getParentRevisionId(RevisionableInterface $entity);

  /**
   * Gets the merge parent revisions ID.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revisionable entity object.
   *
   * @return int|string
   *   The merge parent revision ID.
   */
  public function getMergeParentRevisionId(RevisionableInterface $entity);

  /**
   * Sets the parent revision ID.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revisionable entity object.
   * @param int|string $revision_id
   *   The ID of the parent revision.
   *
   * @return $this
   */
  public function setParentRevisionId(RevisionableInterface $entity, $revision_id);

  /**
   * Sets the merge parent revision ID.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revisionable entity object.
   * @param int|string $revision_id
   *   The ID of the merge parent revision.
   *
   * @return $this
   */
  public function setMergeParentRevisionId(RevisionableInterface $entity, $revision_id);

  /**
   * Gets the lowest common ancestor of two revisions.
   *
   * @param int|string $revision_id_1
   *   The first revision ID.
   * @param int|string $revision_id_2
   *   The second revision ID.
   * @param int|string|null $entity_id
   *   (optional) If the two revisions belong to the same entity, the entity ID
   *   can be used for optimizing the process used to build the revision tree,
   *   otherwise the entire revision history might be loaded into memory.
   *   Defaults to NULL.
   *
   * @return int|string|null
   *   The ID of the lowest common ancestor of the two revisions, or NULL if it
   *   couldn't be found.
   */
  public function getLowestCommonAncestorId($revision_id_1, $revision_id_2, $entity_id = NULL);

}
