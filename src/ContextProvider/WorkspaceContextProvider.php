<?php

namespace Drupal\revision_tree\ContextProvider;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

class WorkspaceContextProvider implements ContextProviderInterface {

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * WorkspaceContextProvider constructor.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
   */
  public function __construct(WorkspaceManagerInterface $workspaceManager) {
    $this->workspaceManager = $workspaceManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $workspace = $this->workspaceManager->getActiveWorkspace();
    $context = [$workspace->id()];
    while ($workspace = $workspace->parent_workspace->entity) {
      $context[] = $workspace->id();
    }
    $context[] = NULL;
    return ['hierarchy' => new Context(new ContextDefinition(), $context)];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return $this->getRuntimeContexts([]);
  }

}
