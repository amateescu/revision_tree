<?php

namespace Drupal\revision_tree\ConflictResolverUI;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ManualConflictResolverForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a new ManualConflictResolverForm object.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param EntityRepositoryInterface $entity_repository
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    WorkspaceManagerInterface $workspaceManager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->workspaceManager = $workspaceManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('workspaces.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'revision_tree_manual_conflict_resolver_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, RevisionableInterface $revision_a = NULL, RevisionableInterface $revision_b = NULL) {
    $options = [
      $revision_a->getRevisionId() => $this->t('@label (@id)', ['@label' => $revision_a->label(), '@id' => $revision_a->getRevisionId()]),
      $revision_b->getRevisionId() => $this->t('@label (@id)', ['@label' => $revision_b->label(), '@id' => $revision_b->getRevisionId()]),
    ];
    $form['revision_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Revision'),
      '#options' => $options,
      '#description' => $this->t('Please select a revision to fix the conflict.')
    ];
    $form['entity_type'] = [
      '#type' => 'value',
      '#value' => $revision_a->getEntityTypeId(),
    ];
    $form['revision_a'] = [
      '#type' => 'value',
      '#value' => $revision_a->getRevisionId(),
    ];
    $form['revision_b'] = [
      '#type' => 'value',
      '#value' => $revision_b->getRevisionId(),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select revision')
    ];
    // For now let this form be submitted in any workspace.
    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type');
    $selected_revision_id = $form_state->getValue('revision_id');
    $revision_a = $form_state->getValue('revision_a');
    $revision_b = $form_state->getValue('revision_b');

    // Create a new revision based on the selected revision.
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $selected_revision = $storage->loadRevision($selected_revision_id);
    $new_revision = $storage->createRevision($selected_revision);

    // Set the workspace to the one we are mergin TO.
    // TODO: Move this out of the resolver form.
    $targetWorkspace = $storage->loadRevision($revision_b)->workspace->entity;
    $new_revision->workspace = $targetWorkspace ? $targetWorkspace->id() : $this->workspaceManager->getActiveWorkspace()->id();

    // When merging revision a to b, we set the revision b as parent and
    // revision a as merge parent.
    $new_revision->revision_parent->target_id = $revision_b;
    $new_revision->revision_parent->merge_target_id = $revision_a;

    // Save the new revision and redirect to its page.
    $new_revision->save();
    $form_state->setRedirectUrl($new_revision->toUrl('revision'));
  }

}
