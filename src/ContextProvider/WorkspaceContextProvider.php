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
    // TODO: Add workspace parent field and use real workspace hierarchy.
    $context = array_unique([$this->workspaceManager->getActiveWorkspace()->id(), 'live', NULL]);
    return ['hierarchy' => new Context(new ContextDefinition(), $context)];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return $this->getRuntimeContexts([]);
  }

}
