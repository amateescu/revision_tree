<?php

namespace Drupal\revision_tree\ConflictResolverUI;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ManualConflictResolverForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'revision_tree_manual_conflict_resolver_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, RevisionableInterface $revisionA = NULL, RevisionableInterface $revisionB = NULL) {
    $options = [
      $revisionA->getRevisionId() => $this->t('@label (@id)', ['@label' => $revisionA->label(), '@id' => $revisionA->getRevisionId()]),
      $revisionB->getRevisionId() => $this->t('@label (@id)', ['@label' => $revisionB->label(), '@id' => $revisionB->getRevisionId()]),
    ];
    $form['revisions'] = [
      '#type' => 'select',
      '#title' => $this->t('Revisions'),
      '#options' => $options,
      '#description' => $this->t('Please select a revision to fix the conflict.')
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select revision')
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo: dupplicate the selected revision?
  }
}
