<?php

namespace Drupal\revision_tree\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\workspaces\Form\WorkspaceFormInterface;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Show association status and handle index rebuilds.
 */
class WorkspaceAssociationsRebuildForm extends ContentEntityForm implements WorkspaceFormInterface {

  /**
   * The workspace entity.
   *
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $entity;

  /**
   * The workspace replication manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The workspace association service.
   *
   * @var WorkspaceAssociationInterface
   */
  protected $workspaceAssociation;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspace_association
   *   The association service.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    WorkspaceAssociationInterface $workspace_association,
    WorkspaceManagerInterface $workspace_manager,
    MessengerInterface $messenger,
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    TimeInterface $time = NULL
  ) {
    $this->messenger = $messenger;
    $this->workspaceAssociation = $workspace_association;
    $this->workspaceManager = $workspace_manager;
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.association'),
      $container->get('workspaces.manager'),
      $container->get('messenger'),
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    foreach (Element::children($form) as $child) {
      if ($child !== 'actions') {
        $form[$child]['#access'] = FALSE;
      }
    }

    // Retrieve and add the form actions array.
    $actions = $this->actionsElement($form, $form_state);
    if (!empty($actions)) {
      $form['actions'] = $actions;
    }

    $form['include_descendants'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include child workspaces'),
      '#description' => $this->t('Also rebuild indices for all child workspaces that might be affected.'),
      '#default_value' => 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $elements = parent::actions($form, $form_state);
    unset($elements['delete']);

    $elements['submit']['#value'] = $this->t('Rebuild @workspace', ['@workspace' => $this->entity->label()]);
    $elements['submit']['#submit'] = ['::submitForm'];

    $elements['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button']],
      '#url' => $this->entity->toUrl('collection'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $workspace_ids = [];
    if ($form_state->getValue('include_descendants')) {
      /** @var \Drupal\workspaces\WorkspaceStorage $storage */
      $storage = $this->entityTypeManager->getStorage('workspace');
      $tree = $storage->loadTree();
      $workspace_ids = array_reverse($tree[$this->entity->id()]->_descendants);
    }

    $workspace_ids[] = $this->entity->id();
    $batch = [
      'title' => $this->t('Rebuilding workspaces'),
      'operations' => [],
      'finished' => [$this, 'batchFinished'],
    ];

    foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
      if ($this->workspaceManager->isEntityTypeSupported($entityType)) {
        foreach (array_reverse($workspace_ids) as $workspace_id) {
          $batch['operations'][] = [[$this, 'batchProcess'], [$entityType->id(), $workspace_id]];
        }
      }
    }

    batch_set($batch);
    /** @var \Zend\Diactoros\Response\RedirectResponse $redirect */
    $redirect = batch_process('/admin/config/workflow/workspaces');
    $form_state->setRedirectUrl(Url::fromUri($redirect->getTargetUrl()));
  }

  /**
   * The batch operation callback.
   *
   * Re-indexes all entities of a given type within a given workspace.
   *
   * @param $entity_type_id
   *   The entity type id.
   * @param $workspace_id
   *   The workspace machine name.
   * @param $context
   *   The batch context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function batchProcess($entity_type_id, $workspace_id, &$context) {
    $this->workspaceAssociation->rebuildAssociations($entity_type_id, $workspace_id);
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $workspace = $this->entityTypeManager->getStorage('workspace')->load($workspace_id);

    $context['message'] = $this->t('Rebuilt entities of type %type in workspace %workspace.', [
      '%type' => $entity_type->getLabel(),
      '%workspace' => $workspace->label(),
    ]);
  }

  /**
   * Finished batch callback.
   */
  public function batchFinished($success, $results, $operations) {
    $this->messenger->addMessage('Rebuild complete.');
  }

}
