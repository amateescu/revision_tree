<?php

namespace Drupal\revision_tree\ConflictResolver;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * Interface for the conflict resolver manager services.
 */
interface ConflictResolverManagerInterface {

  /**
   * Checks if two revisions have a conflict.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $revisionA
   *  The first revision.
   * @param \Drupal\Core\Entity\ContentEntityBase $revisionB
   *  The second revision.
   * @param \Drupal\Core\Entity\ContentEntityBase $commonAncestor
   *  The lowest common ancestor revision.
   * @return bool
   *  TRUE if there is a conflict, FALSE otherwise.
   */
  public function checkConflict(ContentEntityBase $revisionA, ContentEntityBase $revisionB, ContentEntityBase $commonAncestor);

  /**
   * Tries to automatically resolve a conflict.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $revisionA
   *  The first revision.
   * @param \Drupal\Core\Entity\ContentEntityBase $revisionB
   *  The second revision.
   * @param \Drupal\Core\Entity\ContentEntityBase $commonAncestor
   *  The lowest comment ancestor revision.
   * @return \Drupal\Core\Entity\ContentEntityBase | null
   *  If the conflict can be automatically resolved, then it return the new
   *  revision. Otherwise it returns null.
   */
  public function resolveConflict(ContentEntityBase $revisionA, ContentEntityBase $revisionB, ContentEntityBase $commonAncestor);
}
