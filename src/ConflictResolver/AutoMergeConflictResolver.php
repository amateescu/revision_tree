<?php

namespace Drupal\revision_tree\ConflictResolver;

use Drupal\Core\Entity\RevisionableInterface;

/**
 * A conflict resolver which tries to auto-merge the changes.
 */
class AutoMergeConflictResolver implements ConflictResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RevisionableInterface $revision_a, RevisionableInterface $revision_b) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflict(RevisionableInterface $revision_a, RevisionableInterface $revision_b, RevisionableInterface $common_ancestor) {
    // @todo Write an auto-merge conflict resolver strategy.
    return NULL;
  }

}
