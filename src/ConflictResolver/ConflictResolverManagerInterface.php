<?php

namespace Drupal\revision_tree\ConflictResolver;

use Drupal\Core\Entity\RevisionableInterface;

/**
 * Interface for the conflict resolver manager services.
 */
interface ConflictResolverManagerInterface {

  /**
   * Checks if two revisions have a conflict.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_a
   *   The first revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_b
   *   The second revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $common_ancestor
   *   The lowest common ancestor revision.
   *
   * @return bool
   *   TRUE if there is a conflict, FALSE otherwise.
   */
  public function checkConflict(RevisionableInterface $revision_a, RevisionableInterface $revision_b, RevisionableInterface $common_ancestor);

  /**
   * Tries to automatically resolve a conflict.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_a
   *   The first revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_b
   *   The second revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $common_ancestor
   *   The lowest comment ancestor revision.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface | null
   *   Returns a new revision if the conflict can be merged automatically, and
   *   NULL otherwise.
   */
  public function resolveConflict(RevisionableInterface $revision_a, RevisionableInterface $revision_b, RevisionableInterface $common_ancestor);

}
