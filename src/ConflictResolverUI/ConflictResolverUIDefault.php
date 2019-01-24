<?php

namespace Drupal\revision_tree\ConflictResolverUI;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormBuilderInterface;

/**
 * Default conflict resolver UI service which just shows a simple select element
 * to choose one of the two revisions in conflict.
 */
class ConflictResolverUIDefault implements ConflictResolverUIInterface {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a ConflictResolverUIDefault object.
   */
  public function __construct(FormBuilderInterface $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RevisionableInterface $revisionA, RevisionableInterface $revisionB) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function conflictResolverUI(RevisionableInterface $revisionA, RevisionableInterface $revisionB) {
    return $this->formBuilder->getForm(ManualConflictResolverForm::class, $revisionA, $revisionB);
  }
}