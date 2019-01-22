<?php

namespace Drupal\revision_tree\ConflictResolverUI;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * Interface for conflict resolver services.
 */
interface ConflictResolverUIInterface {

  /**
   * Checks if this conflict resolver widget should be used for resolving the
   * conflict between two revisions.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $revisionA
   *  The first revision.
   * @param \Drupal\Core\Entity\ContentEntityBase $revisionB
   *  The second revision.
   * @return bool
   *  TRUE if the conflict resolver widget should be used, FALSE otherwise.
   */
  public function applies(ContentEntityBase $revisionA, ContentEntityBase $revisionB);

  /**
   * Renders the conflict resolver UI widget.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $revisionA
   *  The first revision.
   * @param \Drupal\Core\Entity\ContentEntityBase $revisionB
   *  The second revision.
   * @return array | null
   *  A render array representing the UI.
   */
  public function render(ContentEntityBase $revisionA, ContentEntityBase $revisionB);
}
