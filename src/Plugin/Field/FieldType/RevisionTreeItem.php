<?php

namespace Drupal\revision_tree\Plugin\Field\FieldType;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;

/**
 * Defines the 'revision_tree' field type.
 *
 * @FieldType(
 *   id = "revision_tree",
 *   label = @Translation("Revision tree"),
 *   description = @Translation("An entity field containing a revision tree item."),
 *   no_ui = TRUE,
 *   cardinality = 1,
 *   constraints = {"ValidRevisionTreeReference" = {}}
 * )
 */
class RevisionTreeItem extends FieldItemBase {

  /**
   * Whether the parent revision ID should be assigned automatically or not.
   *
   * @var bool
   */
  protected $assignParentAutomatically = TRUE;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $target_type = \Drupal::entityTypeManager()->getDefinition($field_definition->getTargetEntityTypeId());

    $target_id_data_type = 'string';
    if ($target_type->entityClassImplements(FieldableEntityInterface::class)) {
      $id_definition = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions($target_type->id())[$target_type->getKey('revision')];
      if ($id_definition->getType() === 'integer') {
        $target_id_data_type = 'integer';
      }
    }

    $target_id_definition = DataReferenceTargetDefinition::create($target_id_data_type)
      ->setLabel(new TranslatableMarkup('Parent revision ID'));

    $merge_target_id_definition = DataReferenceTargetDefinition::create($target_id_data_type)
      ->setLabel(new TranslatableMarkup('Merged revision ID'));

    if ($target_id_data_type === 'integer') {
      $target_id_definition->setSetting('unsigned', TRUE);
      $merge_target_id_definition->setSetting('unsigned', TRUE);
    }
    $properties['target_id'] = $target_id_definition;
    $properties['merge_target_id'] = $merge_target_id_definition;

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $target_id_property = static::propertyDefinitions($field_definition)['target_id'];

    if ($target_id_property->getDataType() === 'integer') {
      $columns = [
        'target_id' => [
          'description' => 'The ID of the parent revision.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'merge_target_id' => [
          'description' => 'The ID of the merged revision.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
      ];
    }
    else {
      $columns = [
        'target_id' => [
          'description' => 'The ID of the parent revision.',
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
        'merge_target_id' => [
          'description' => 'The ID of the merged revision.',
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
      ];
    }

    $schema = [
      'columns' => $columns,
      'indexes' => [
        'target_id' => ['target_id'],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // An empty item is always a root.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'target_id';
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // When the parent revision ID is assigned manually, we have to skip the
    // auto-assign code from ::preSave().
    if ($property_name === 'target_id') {
      $this->assignParentAutomatically = FALSE;
    }

    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
    $entity = $this->getEntity();

    // Set the parent revision ID automatically when we create a new revision.
    if ($this->assignParentAutomatically && $entity->isNewRevision()) {
      $this->writePropertyValue('target_id', $entity->getLoadedRevisionId());
    }
  }

}
