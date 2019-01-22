<?php

namespace Drupal\revision_tree\ConflictResolverUI;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * Interface for the conflict resolver ui manager services.
 */
interface ConflictResolverUIManagerInterface {

  /**
   * Render the conflict resolver UI.
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
