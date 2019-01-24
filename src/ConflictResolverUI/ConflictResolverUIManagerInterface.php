<?php

namespace Drupal\revision_tree\ConflictResolverUI;

use Drupal\Core\Entity\RevisionableInterface;

/**
 * Interface for the conflict resolver ui manager services.
 */
interface ConflictResolverUIManagerInterface {

  /**
   * Builds the conflict resolver UI.
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
