<?php

namespace Drupal\revision_tree;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\workspaces\WorkspaceAssociation;
use Drupal\workspaces\WorkspaceInterface;

class RevisionTreeWorkspaceAssociation extends WorkspaceAssociation implements RevisionTreeWorkspaceAssociationInterface {

  /**
   * {@inheritdoc}
   */
  public function trackEntity(RevisionableInterface $entity, WorkspaceInterface $workspace) {
    /** @var \Drupal\workspaces\WorkspaceStorage $workspace_storage */
    $workspace_storage = $this->entityTypeManager->getStorage('workspace');

    // Determine all workpaces that might be affected by this change.
    $tree = $workspace_storage->loadTree();
    $affected_workspaces = $tree[$workspace->id()]->_descendants;
    $affected_workspaces[] = $workspace->id();

    $parent_revision = $entity->revision_parent->target_revision_id;

    $merge_parent_revision = NULL;
    if ($merge_parent = $entity->revision_merge_parent->target_revision_id) {
      /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage(
        $entity->getEntityTypeId()
      );
      $merge_parent_entity = $storage->loadRevision($merge_parent);
      // Only update the tracked revision for source workspace it was a merge
      // from child to parent workspace Else the child keeps its own revision.
      if ($merge_parent_entity->workspace->entity->parent->entity->id() === $workspace->id()) {
        $merge_parent_revision = $merge_parent;
      }
    }

    // Update all affected workspaces that were tracking the current revision.
    // This means they are inheriting content and should be updated.
    if ($parent_revision) {
      $this->database->update(static::TABLE)
        ->fields([
          'target_entity_revision_id' => $entity->getRevisionId(),
          'target_entity_type_id' => $entity->getEntityTypeId(),
          'target_entity_id' => $entity->id(),
        ])
        ->condition('workspace', $affected_workspaces, 'IN')
        ->condition('target_entity_type_id', $entity->getEntityTypeId())
        ->condition('target_entity_id', $entity->id())
        // Only update child workspaces if they have the same initial
        // revision, which means they are currently inheriting content.
        // CHANGE: Also update indices for merge parents.
        ->condition('target_entity_revision_id', array_filter([$parent_revision, $merge_parent_revision]), 'IN')
        ->execute();
    }

    // Insert a new index entry for each workspace that should be affected but
    // doesn't have an entry yet.
    $missing_workspaces = array_diff($affected_workspaces, $this->getEntityTrackingWorkspaceIds($entity));
    if ($missing_workspaces) {
      $insert_query = $this->database->insert(static::TABLE)
        ->fields([
          'workspace',
          'target_entity_revision_id',
          'target_entity_type_id',
          'target_entity_id',
        ]);
      foreach ($missing_workspaces as $workspace_id) {
        $insert_query->values([
          'workspace' => $workspace_id,
          'target_entity_type_id' => $entity->getEntityTypeId(),
          'target_entity_id' => $entity->id(),
          'target_entity_revision_id' => $entity->getRevisionId(),
        ]);
      }
      $insert_query->execute();
    }
  }

  /**
   * Build a query that lists all revisions overridden in a given workspace.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   *   The entity type.
   * @param string $source_workspace
   *   The source workspace to calculate the diff for.
   * @param string|null $parent_workspace
   *   (optional) The parent workspace of the source workspace.
   * @param array|null $entity_ids
   *   (optional) A list of entity id's to restrict this operation to.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   A select query that can be used to insert new index rows.
   */
  protected function buildOverridesQuery(
    ContentEntityTypeInterface $entity_type,
    $source_workspace,
    $parent_workspace = NULL,
    $entity_ids = NULL) {

    // Retrieve correct table and field names.
    $table = $entity_type->getRevisionTable();
    $workspace_field = $entity_type->getRevisionMetadataKey('workspace');
    $id_field = $entity_type->getKey('id');
    $revision_field = $entity_type->getKey('revision');
    $revision_parent_field = $entity_type->getRevisionMetadataKey('revision_parent');
    $revision_merge_parent_field = $entity_type->getRevisionMetadataKey('revision_merge_parent');

    $query = $this->database->select($table, 'revision');
    $query->condition("revision.$workspace_field", $source_workspace);

    // If necessary, restrict this operation to a given set of entities.
    if ($entity_ids) {
      $query->condition("revision.$id_field", $entity_ids, 'IN');
    }

    // Join the revision table to filter for tree leaves.
    $join_condition = $query->andConditionGroup();
    $query->leftJoin($table, 'child', $join_condition);
    $join_condition->where("child.$id_field = revision.$id_field");

    // Reduce the comparing revisions to the source and potentially the
    // parent workspace.
    $join_condition->condition("child.$workspace_field", array_filter([$source_workspace, $parent_workspace]), 'IN');

    // Reduce the result set to revisions that are not parent of another
    // revision in the current or parent workspace.
    // This ensures that only child to parent merges actually close a leaf. For
    // all other merge operations, the merged workspaces active revision stays
    // the same.
    $leaf_condition = $join_condition->orConditionGroup();
    $leaf_condition->where("child.$revision_parent_field = revision.$revision_field");
    $leaf_condition->where("child.$revision_merge_parent_field = revision.$revision_field");
    $join_condition->where($leaf_condition);
    $query->isNull("child.$revision_field");

    // Artificially inject workspace and entity type id into the result set.
    $query->addExpression(':workspace', 'workspace', [
      ':workspace' => $source_workspace,
    ]);
    $query->addExpression(':entity_type', 'target_entity_type_id', [
      ':entity_type' => $entity_type->id(),
    ]);

    // Add the calculated ids and revisions to the result set.
    $query->addField('revision', $id_field, 'target_entity_id');
    $query->addField('revision', $revision_field, 'target_entity_revision_id');

    return $query;
  }


  /**
   * {@inheritdoc}
   */
  public function rebuildAssociations($entity_type_id, $workspace_id, $entity_ids = NULL) {
    /** @var \Drupal\workspaces\WorkspaceStorage $workspace_storage */
    $workspace_storage = $this->entityTypeManager->getStorage('workspace');

    /** @var WorkspaceInterface $workspace */
    $workspace = $workspace_storage->load($workspace_id);
    /** @var WorkspaceInterface $parent_workspace */
    $parent_workspace = $workspace->parent->entity;

    /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

    $this->deleteAssociations($workspace_id, $entity_type_id, $entity_ids);

    if ($parent_workspace) {
      // If there is a parent workspace, copy over all index rows and then update
      // the overridden ones.

      // Prepare a clone query
      $clone_query = $this->database
        ->select('workspace_association', 'wa');

      // Set the workspace field to the current workspace.
      $clone_query->addExpression(':workspace', 'workspace', [
        ':workspace' => $workspace_id,
      ]);

      // Take over all other index fields.
      $clone_query->fields('wa', [
        'target_entity_type_id',
        'target_entity_id',
        'target_entity_revision_id',
      ]);

      // Make sure we only clone index entries from the parent workspace.
      $clone_query->condition('workspace', $parent_workspace->id());

      // If necessary, reduce the cloned entries to the specified set of entities.
      if ($entity_ids) {
        $clone_query->condition('target_entity_id', $entity_ids, 'IN');
      }

      $transaction = $this->database->startTransaction();
      try {
        // Execute the query and effectively clone the index entries.
        $this->database
          ->insert('workspace_association')
          ->from($clone_query)
          ->execute();


        // Get an update query that results in a set of revisions that are custom
        // to the current workspace.
        $result = $this->buildOverridesQuery($entity_type, $workspace_id, $parent_workspace->id())->execute();

        // Manually insert all these entries.
        // Since this only happens for sub-workspaces the number of singular
        // inserts shouldn't be too bad in this case. For extreme cases where a
        // Workspace maintains a lot of overrides, this might be optimized into
        // a more efficient REPLACE INTO ... operation with an implementation
        // specific to the database system in use.
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
          $this->database->merge('workspace_association')
            ->fields([
              'target_entity_revision_id' => $row['target_entity_revision_id'],
            ])
            ->keys([
              'workspace' => $row['workspace'],
              'target_entity_type_id' => $row['target_entity_type_id'],
              'target_entity_id' => $row['target_entity_id'],
            ])
            ->execute();
        }
      } catch (\Exception $e) {
        $transaction->rollBack();
        watchdog_exception('workspaces-index-rebuild', $e);
      }
    }
    else {
      // If there is no parent workspace, we can insert the update query
      // directly, since there are no existing index entries.
      $this->database
        ->insert('workspace_association')
        ->from($this->buildOverridesQuery($entity_type, $workspace_id))
        ->execute();
    }
  }
}
