<?php

namespace Drupal\revision_tree;

use Drupal\Component\Graph\Graph;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the revision tree entity type handler.
 *
 * @todo This should be moved to a method on
 *   \Drupal\Core\Entity\RevisionableStorageInterface.
 */
class SqlRevisionTreeHandler implements EntityHandlerInterface, RevisionTreeHandlerInterface {

  /**
   * Information about the entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * An array containing the ancestor of each revision, keyed by entity ID.
   *
   * @var array
   */
  protected $ancestor_ids = [];

  /**
   * Constructs a SqlRevisionTreeHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityType = $entity_type;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLowestCommonAncestor(RevisionableInterface $entity, $first_revision_id, $second_revision_id) {
    $ancestors = &$this->ancestor_ids[$entity->id()];

    if (empty($ancestors)) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity_type = $entity->getEntityType();
      /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage($entity_type->id());
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
      $table_mapping = $storage->getTableMapping();

      $revision_tree_table = $table_mapping->getFieldTableName($entity_type->getRevisionMetadataKey('revision_parent'));
      $field_columns = $table_mapping->getColumnNames($entity_type->getKey('id'));
      $id_column = reset($field_columns);
      $field_columns = $table_mapping->getColumnNames($entity_type->getKey('revision'));
      $revision_id_column = reset($field_columns);
      $parent_revision_id_column = $table_mapping->getColumnNames($entity_type->getRevisionMetadataKey('revision_parent'))['target_id'];
      $merge_revision_id_column = $table_mapping->getColumnNames($entity_type->getRevisionMetadataKey('revision_parent'))['merge_target_id'];

      $query = $this->database->select($revision_tree_table, 't');
      $query->condition($id_column, $entity->id(), '=');
      $query->addField('t', $revision_id_column, 'id');
      $query->addField('t', $parent_revision_id_column, 'parent_id');
      $query->addField('t', $merge_revision_id_column, 'merge_parent_id');
      $query->orderBy($revision_id_column, 'ASC');

      $graph = [];
      foreach ($query->execute() as $row) {
        // We store "ancestors and self" IDs, so we need to add the item itself
        // to the list.
        $ancestors[$row->id] = [$row->id];

        if ($row->parent_id) {
          $graph[$row->id]['edges'][$row->parent_id] = TRUE;
        }

        if ($row->merge_parent_id) {
          $graph[$row->id]['edges'][$row->merge_parent_id] = TRUE;
        }
      }

      $processed_graph = (new Graph($graph))->searchAndSort();
      foreach ($processed_graph as $revision_id => $values) {
        // Keep the ancestors of this revision as well as its own ID.
        $graph_paths = isset($values['paths']) ? array_keys($values['paths']) : [];
        $ancestors[$revision_id] = array_merge($ancestors[$revision_id], $graph_paths);
      }

      // Free up memory early.
      $processed_graph = NULL;
      $graph = NULL;
      $graph_paths = NULL;
    }

    if (!isset($ancestors[$first_revision_id])) {
      throw new \InvalidArgumentException("The specified revision ($first_revision_id) does not exist.");
    }
    if (!isset($ancestors[$second_revision_id])) {
      throw new \InvalidArgumentException("The specified revision ($second_revision_id) does not exist.");
    }
    if (empty($ancestors[$first_revision_id]) || empty($ancestors[$second_revision_id])) {
      return NULL;
    }

    $common_ancestors = array_intersect($ancestors[$first_revision_id], $ancestors[$second_revision_id]);
    // The ancestors are ordered by ascending weight, so the lowest common
    // ancestor is the first element of the array.
    return reset($common_ancestors);
  }

}
