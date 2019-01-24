<?php

namespace Drupal\revision_tree\ConflictResolver;

use Drupal\Core\Entity\RevisionableInterface;

/**
 * A basic implementation of a conflict resolver service.
 */
class ConflictResolverDefault implements ConflictResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RevisionableInterface $revisionA, RevisionableInterface $revisionB, RevisionableInterface $commonAncestor) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflict(RevisionableInterface $revisionA, RevisionableInterface $revisionB, RevisionableInterface $commonAncestor) {
    return NULL;
  }
}
