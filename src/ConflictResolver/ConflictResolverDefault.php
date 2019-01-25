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
  public function applies(RevisionableInterface $revision_a, RevisionableInterface $revision_b, RevisionableInterface $common_ancestor) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflict(RevisionableInterface $revision_a, RevisionableInterface $revision_b, RevisionableInterface $common_ancestor) {
    return NULL;
  }

}
