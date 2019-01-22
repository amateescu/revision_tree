<?php

namespace Drupal\revision_tree;

use Drupal\Core\Entity\RevisionableInterface;

/**
 * Defines an interface for revision tree entity handlers.
 */
interface RevisionTreeHandlerInterface {

  /**
   * Gets the lowest common ancestor of two revision IDs.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   The entity which contains the two revisions.
   * @param int|string $first_revision_id
   *   The first revision ID.
   * @param int|string $second_revision_id
   *   The second revision ID.
   *
   * @return int|string|null
   *   The lowest common ancestor of the two revisions, or NULL if couldn't be
   *   found.
   */
  public function getLowestCommonAncestor(RevisionableInterface $entity, $first_revision_id, $second_revision_id);

}
