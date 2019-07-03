<?php

namespace Drupal\revision_tree;

use Drupal\workspaces\WorkspaceAssociationInterface;

interface RevisionTreeWorkspaceAssociationInterface extends WorkspaceAssociationInterface {
  /**
   * Delete and rebuild associations for a given set of workspaces.
   *
   * For performance reasons the rebuild process relies on the parent workspace
   * being correctly indexed. This should be done in a batch process beforehand.
   *
   * @param string $entity_type_id
   *   The entity type id to rebuild the association index for.
   * @param  $workspace_id
   *   The workspace machine name to rebuild the association index for.
   *
   * @return mixed
   */
  public function rebuildAssociations($entity_type_id, $workspace_id);
}

