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
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_a
   *   The first revision.
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_b
   *   The second revision.
   *
   * @return array|null
   *   A render array representing the UI.
   */
  public function conflictResolverUI(RevisionableInterface $revision_a, RevisionableInterface $revision_b);

}
