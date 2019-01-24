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
   * @param \Drupal\Core\Entity\RevisionableInterface $revisionA
   *  The first revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $revisionB
   *  The second revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $commonAncestor
   *  The lowest common ancestor revision.
   * @return bool
   *  TRUE if there is a conflict, FALSE otherwise.
   */
  public function checkConflict(RevisionableInterface $revisionA, RevisionableInterface $revisionB, RevisionableInterface $commonAncestor);

  /**
   * Tries to automatically resolve a conflict.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revisionA
   *  The first revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $revisionB
   *  The second revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $commonAncestor
   *  The lowest comment ancestor revision.
   * @return \Drupal\Core\Entity\RevisionableInterface | null
   *  If the conflict can be automatically resolved, then it return the new
   *  revision. Otherwise it returns null.
   */
  public function resolveConflict(RevisionableInterface $revisionA, RevisionableInterface $revisionB, RevisionableInterface $commonAncestor);
}
