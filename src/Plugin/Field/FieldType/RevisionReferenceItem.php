<?php

namespace Drupal\revision_tree\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;

/**
 * Defines the 'revision_reference' field type.
 *
 * @FieldType(
 *   id = "revision_reference",
 *   label = @Translation("Revision reference"),
 *   description = @Translation("An entity field containing a revision reference."),
 *   no_ui = TRUE,
 * )
 */
class RevisionReferenceItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $target_type = \Drupal::entityTypeManager()->getDefinition($field_definition->getTargetEntityTypeId());

    /** @var \Drupal\Core\Field\BaseFieldDefinition $revision_field_definition */
    $revision_field_definition = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions($target_type->id())[$target_type->getKey('revision')];
    $revision_main_property = $revision_field_definition->getPropertyDefinition($revision_field_definition->getMainPropertyName());

    $properties['target_revision_id'] = DataReferenceTargetDefinition::create($revision_main_property->getDataType())
      ->setLabel(new TranslatableMarkup('Revision ID'))
      ->setSettings($revision_main_property->getSettings());

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'target_revision_id';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $target_type = \Drupal::entityTypeManager()->getDefinition($field_definition->getTargetEntityTypeId());

    /** @var \Drupal\Core\Field\BaseFieldDefinition $revision_field_definition */
    $revision_field_definition = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions($target_type->id())[$target_type->getKey('revision')];
    $revision_column_schema = $revision_field_definition->getSchema()['columns'][$revision_field_definition->getMainPropertyName()];

    $schema = [
      'columns' => [
        'target_revision_id' => [
            'description' => 'The ID of the referenced revision.',
          ] + $revision_column_schema,
      ],
      'indexes' => [
        'target_revision_id' => ['target_revision_id'],
      ],
    ];

    return $schema;
  }

}
