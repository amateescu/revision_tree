<?php

namespace Drupal\revision_tree\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if revision tree references are valid.
 */
class ValidRevisionTreeReferenceConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ValidReferenceConstraintValidator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Field\FieldItemInterface $value */
    /** @var \Drupal\Core\Entity\Plugin\Validation\Constraint\ValidRevisionTreeReferenceConstraint $constraint */
    if (!isset($value)) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface $entity */
    $entity = $value->getEntity();
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());

    $field_name = $value->getFieldDefinition()->getName();

    $parent_revision_id = $value->target_id;
    $merge_revision_id = $value->merge_target_id;

    // First, check that we are not trying to change the parent for an existing
    // revision.
    if (!$entity->isNew() && !$entity->isNewRevision()) {
      $original = $storage->loadRevision($entity->getLoadedRevisionId());
      if ($parent_revision_id != $original->{$field_name}->target_id || $merge_revision_id != $original->{$field_name}->merge_target_id) {
        $this->context->addViolation($constraint->readOnlyMessage);
      }
    }

    if ($parent_revision_id || $merge_revision_id) {
      // Second, check that the parent and the merge revision are not the same.
      if ($parent_revision_id == $merge_revision_id) {
        $this->context->addViolation($constraint->sameRevisionMessage);
      }

      // Third, check that the assigned parent and the merge parent actually
      // exist.
      $revision_ids_to_check = array_filter([$parent_revision_id, $merge_revision_id]);
      $revisions = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadMultipleRevisions($revision_ids_to_check);
      if ($parent_revision_id && (!isset($revisions[$parent_revision_id]) || $revisions[$parent_revision_id]->id() != $entity->id())) {
        $this->context->addViolation($constraint->message, ['%revision_id' => $parent_revision_id]);
      }
      if ($merge_revision_id && (!isset($revisions[$merge_revision_id]) || $revisions[$merge_revision_id]->id() != $entity->id())) {
        $this->context->addViolation($constraint->message, ['%revision_id' => $merge_revision_id]);
      }
    }
  }

}
