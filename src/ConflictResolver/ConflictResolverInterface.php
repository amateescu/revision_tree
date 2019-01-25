<?php

namespace Drupal\revision_tree\ConflictResolver;

use Drupal\Core\Entity\RevisionableInterface;

/**
 * Interface for conflict resolver services.
 */
interface ConflictResolverInterface {

  /**
   * Checks if this conflict resolver can be used for the passed-in revisions.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_a
   *   The first revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_b
   *   The second revision.
   *
   * @return bool
   *   TRUE if the conflict resolver should be used, FALSE otherwise.
   */
  public function applies(RevisionableInterface $revision_a, RevisionableInterface $revision_b);

  /**
   * Tries to resolve a conflict between two revisions.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_a
   *   The first revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_b
   *   The second revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $common_ancestor
   *   The lowest comment ancestor revision.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface|null
   *   Returns a new revision if the conflict has been resolved, NULL otherwise.
   */
  public function resolveConflict(RevisionableInterface $revision_a, RevisionableInterface $revision_b, RevisionableInterface $common_ancestor);

}
