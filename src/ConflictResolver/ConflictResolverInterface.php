<?php

namespace Drupal\revision_tree\ConflictResolver;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * Interface for conflict resolver services.
 */
interface ConflictResolverInterface {

  /**
   * Checks if this conflict resolver should be used for resolving the conflict
   * between two revisions.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $revisionA
   *  The first revision.
   * @param \Drupal\Core\Entity\ContentEntityBase $revisionB
   *  The second revision.
   * @param \Drupal\Core\Entity\ContentEntityBase $commonAncestor
   *  The lowest common ancestor.
   * @return bool
   *  TRUE if the conflict resolver should be used, FALSE otherwise.
   */
  public function applies(ContentEntityBase $revisionA, ContentEntityBase $revisionB, ContentEntityBase $commonAncestor);

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
