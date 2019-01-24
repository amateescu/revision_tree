<?php

namespace Drupal\revision_tree\ConflictResolverUI;

use Drupal\Core\Entity\RevisionableInterface;

/**
 * Interface for conflict resolver services.
 */
interface ConflictResolverUIInterface {

  /**
   * Checks if this conflict resolver widget should be used for resolving the
   * conflict between two revisions.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revisionA
   *  The first revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $revisionB
   *  The second revision.
   * @return bool
   *  TRUE if the conflict resolver widget should be used, FALSE otherwise.
   */
  public function applies(RevisionableInterface $revisionA, RevisionableInterface $revisionB);

  /**
   * Builds the conflict resolver UI widget.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revisionA
   *  The first revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $revisionB
   *  The second revision.
   * @return array | null
   *  A render array representing the UI.
   */
  public function conflictResolverUI(RevisionableInterface $revisionA, RevisionableInterface $revisionB);
}
