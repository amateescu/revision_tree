<?php

namespace Drupal\revision_tree\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if revision tree references are valid.
 */
class ValidRevisionParentConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ValidRevisionTreeReferenceConstraintValidator object.
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
  public function validate($entity, Constraint $constraint) {
    assert($entity instanceof RevisionableInterface);

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $revision_tree = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'revision_tree');
    $revision_parent_field_name = $entity->getEntityType()->getRevisionMetadataKeys(FALSE)['revision_parent'];
    $revision_merge_parent_field_name = $entity->getEntityType()->getRevisionMetadataKeys(FALSE)['revision_merge_parent'];

    // First, check that we are not trying to change the parent for an existing
    // revision.
    if (!$entity->isNew() && !$entity->isNewRevision()) {
      $original = $storage->loadRevision($entity->getLoadedRevisionId());
      foreach ([$revision_parent_field_name, $revision_merge_parent_field_name] as $field_name) {
        if (!$entity->get($field_name)->equals($original->get($field_name))) {
          $this->context->buildViolation($constraint->readOnlyMessage)
            ->atPath($field_name)
            ->addViolation();
        }
      }
    }

    $parent_revision_id = $revision_tree->getParentRevisionId($entity);
    $revision_merge_parent_id = $revision_tree->getMergeParentRevisionId($entity);
    $parent_revision_ids = array_filter([$parent_revision_id, $revision_merge_parent_id]);
    if ($parent_revision_ids) {
      // Second, check that the revision parents are not the same.
      if (count($parent_revision_ids) === 2 && (string) $parent_revision_ids[0] === (string) $parent_revision_ids[1]) {
        $this->context->buildViolation($constraint->sameRevisionMessage)
          ->atPath($revision_parent_field_name)
          ->addViolation();
      }

      // Third, check that the assigned parents exist.
      $revision_ids_to_check = $parent_revision_ids;
      $revisions = $storage->loadMultipleRevisions($revision_ids_to_check);
      foreach ($revision_ids_to_check as $revision_id) {
        if (!isset($revisions[$revision_id])) {
          if ($revision_id == $parent_revision_id) {
            $this->context->buildViolation($constraint->message, ['%revision_id' => $revision_id])
              ->atPath($revision_parent_field_name)
              ->addViolation();
          }
          else {
            $this->context->buildViolation($constraint->message, ['%revision_id' => $revision_id])
              ->atPath($revision_merge_parent_field_name)
              ->addViolation();
          }
        }
      }
    }
  }

}
