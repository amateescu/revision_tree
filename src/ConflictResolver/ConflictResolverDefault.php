<?php

namespace Drupal\revision_tree\ConflictResolver;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * A basic implementation of a conflict resolver service.
 */
class ConflictResolverDefault implements ConflictResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(ContentEntityBase $revisionA, ContentEntityBase $revisionB, ContentEntityBase $commonAncestor) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflict(ContentEntityBase $revisionA, ContentEntityBase $revisionB, ContentEntityBase $commonAncestor) {
    return NULL;
  }
}
