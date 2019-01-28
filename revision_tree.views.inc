<?php

/**
 * @file
 * Contains Views hooks.
 */

/**
 * Implements hook_views_data_alter().
 */
function revision_tree_views_data_alter(array &$data) {
  $entity_type_manager = \Drupal::entityTypeManager();
  $entity_types = $entity_type_manager->getDefinitions();

  // Provide a relationship for each entity type that has a base table.
  foreach ($entity_types as $type => $entity_type) {
    $entity_revision_table = $entity_type->getRevisionDataTable() ?: $entity_type->getRevisionTable();
    $t_args = [
      '@entity_type' => $entity_type->getLabel(),
    ];

    $data[$entity_revision_table]['workspace_compare'] = [
      'title' => t('Workspace Compare for @entity_type', $t_args),
      'help' => t('Compare revisions of @entity_type.', $t_args),
      'field' => [
        'id' => 'workspace_compare',
        'label' => t('Comparison result'),
      ],
      'filter' => [
        'id' => 'workspace_compare',
        'label' => t('Workspace Compare filter'),
      ],
    ];
  }
}
