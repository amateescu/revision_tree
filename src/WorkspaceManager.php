<?php

namespace Drupal\revision_tree;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\workspaces\WorkspaceManager as OriginalWorkspaceManager;

/**
 * Fixes a blacklist matching bug in the original WorkspaceManager.
 *
 * // TODO: Move this to a patch.
 */
class WorkspaceManager extends OriginalWorkspaceManager {

  /**
   * @todo: added the content_moderation_state entity. fix this properly?
   */
  protected $blacklist = [
    'workspace_association',
    'workspace',
  ];

  /**
   * {@inheritdoc}
   */
  public function isEntityTypeSupported(EntityTypeInterface $entity_type) {
    if (!in_array($entity_type->id(), $this->blacklist)
      // @todo: removed the "EntityPublished" check. discuss?
      && $entity_type->isRevisionable()) {
      return TRUE;
    }
    $this->blacklist[$entity_type->id()] = $entity_type->id();
    return FALSE;
  }

}
