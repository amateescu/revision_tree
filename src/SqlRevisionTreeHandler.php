<?php

namespace Drupal\revision_tree;

use Drupal\Component\Graph\Graph;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the default SQL implementation for the entity revision tree handler.
 */
class SqlRevisionTreeHandler implements EntityRevisionTreeHandlerInterface, EntityHandlerInterface {

  /**
   * Information about the entity type.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface
   */
  protected $entityType;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface
   */
  protected $storage;

  /**
   * The field storage definitions for this entity type.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   */
  protected $fieldStorageDefinitions;

  /**
   * Name of the entity's ID field.
   *
   * @var string
   */
  protected $idKey;

  /**
   * Name of the entity's revision ID field.
   *
   * @var string
   */
  protected $revisionIdKey;

  /**
   * Name of the entity's parent_revision field.
   *
   * @var string
   */
  protected $revisionParentKey;

  /**
   * Name of the entity's revision_merge_parent field.
   *
   * @var string
   */
  protected $revisionMergeParentKey;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new SqlRevisionTreeHandler.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   An entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manger.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository
   *   The last installed schema repository.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository, Connection $database) {
    $this->entityType = $entity_type;
    $this->storage = $entity_type_manager->getStorage($entity_type->id());
    $this->fieldStorageDefinitions = $entity_last_installed_schema_repository->getLastInstalledFieldStorageDefinitions($entity_type->id());
    $this->database = $database;

    $this->idKey = $this->entityType->getKey('id');
    $this->revisionIdKey = $this->entityType->getKey('revision');
    $this->revisionParentKey = $this->entityType->getRevisionMetadataKey('revision_parent');
    $this->revisionMergeParentKey = $this->entityType->getRevisionMetadataKey('revision_merge_parent');

    assert($this->storage instanceof SqlEntityStorageInterface);
    assert($this->fieldStorageDefinitions[$this->revisionParentKey]->getType() === 'revision_reference');
    assert($this->fieldStorageDefinitions[$this->revisionMergeParentKey]->getType() === 'revision_reference');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('entity.last_installed_schema.repository'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getParentRevisionId(RevisionableInterface $entity) {
    return $entity->get($this->revisionParentKey)->target_revision_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getMergeParentRevisionId(RevisionableInterface $entity) {
    return $entity->get($this->revisionMergeParentKey)->target_revision_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentRevisionId(RevisionableInterface $entity, $revision_id) {
    $entity->get($this->revisionParentKey)->target_revision_id = $revision_id;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setMergeParentRevisionId(RevisionableInterface $entity, $revision_id) {
    $entity->get($this->revisionMergeParentKey)->target_revision_id = $revision_id;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLowestCommonAncestorId($revision_id_1, $revision_id_2, $entity_id = NULL) {
    if ($revision_id_1 == $revision_id_2) {
      return $revision_id_1;
    }

    $revision_parents = $this->getAllRevisionParentsInfo($entity_id);

    $graph = [];
    foreach ($revision_parents as $revision_id => $info) {
      if ($parent_id = $info['parent_id']) {
        $graph[$revision_id]['edges'][$parent_id] = TRUE;
      }

      if ($merge_parent_id = $info['merge_parent_id']) {
        $graph[$revision_id]['edges'][$merge_parent_id] = TRUE;
      }
    }

    $revision_graph = (new Graph($graph))->searchAndSort();

    // Check if one of the revision IDs is in the ancestry path of the other.
    if (isset($revision_graph[$revision_id_1]) && array_key_exists($revision_id_2, $revision_graph[$revision_id_1]['paths'])) {
      return $revision_id_2;
    }
    if (isset($revision_graph[$revision_id_2]) && array_key_exists($revision_id_1, $revision_graph[$revision_id_2]['paths'])) {
      return $revision_id_1;
    }

    // Get the intersection of the ancestors for both vertices, and select the
    // ancestor with the highest weight.
    if (isset($revision_graph[$revision_id_1]) && isset($revision_graph[$revision_id_2])) {
      if ($common_ancestors = array_intersect_key($revision_graph[$revision_id_1]['paths'], $revision_graph[$revision_id_2]['paths'])) {
        $subgraph = array_intersect_key($revision_graph, $common_ancestors);
        uasort($subgraph, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

        // The ancestors are ordered by ascending weight, so the lowest common
        // ancestor is the first element of the array.
        return key($subgraph);
      }
    }

    return NULL;
  }

  /**
   * Retrieves a list of revision parents for a given entity.
   *
   * @param int|string|null $entity_id
   *   The entity ID.
   *
   * @return array
   *   A multi-dimensional array keyed by revision ID, containing an array with
   *   the following structure:
   *   - id: The revision ID;
   *   - parent_id: The parent revision ID;
   *   - merge_parent_id: The merge parent revision ID.
   */
  protected function getAllRevisionParentsInfo($entity_id) {
    $table_mapping = $this->storage->getTableMapping();

    $parent_revision_table = $table_mapping->getFieldTableName($this->revisionParentKey);
    $revision_merge_parent_table = $table_mapping->getFieldTableName($this->revisionMergeParentKey);
    assert($parent_revision_table === $revision_merge_parent_table);

    $id_column = $table_mapping->getFieldColumnName($this->fieldStorageDefinitions[$this->idKey], $this->fieldStorageDefinitions[$this->idKey]->getMainPropertyName());
    $revision_id_column = $table_mapping->getFieldColumnName($this->fieldStorageDefinitions[$this->revisionIdKey], $this->fieldStorageDefinitions[$this->revisionIdKey]->getMainPropertyName());
    $parent_revision_id_column = $table_mapping->getColumnNames($this->revisionParentKey)[$this->fieldStorageDefinitions[$this->revisionParentKey]->getMainPropertyName()];
    $merge_revision_id_column = $table_mapping->getColumnNames($this->revisionMergeParentKey)[$this->fieldStorageDefinitions[$this->revisionMergeParentKey]->getMainPropertyName()];

    $query = $this->database->select($parent_revision_table, 't');
    if ($entity_id) {
      $query->condition($id_column, $entity_id, '=');
    }
    $query->addField('t', $revision_id_column, 'id');
    $query->addField('t', $parent_revision_id_column, 'parent_id');
    $query->addField('t', $merge_revision_id_column, 'merge_parent_id');
    $query->orderBy($revision_id_column, 'ASC');

    return $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

}
