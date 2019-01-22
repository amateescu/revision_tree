<?php

namespace Drupal\revision_tree\ConflictResolverUI;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * Default conflict resolver UI service which just shows a simple select element
 * to choose one of the two revisions in conflict.
 */
class ConflictResolverUIDefault implements ConflictResolverUIInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(ContentEntityBase $revisionA, ContentEntityBase $revisionB) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ContentEntityBase $revisionA, ContentEntityBase $revisionB) {
    return[
      '#type' => 'item',
      '#value' => 'Conflict resolver UI!'
    ];
  }
}
