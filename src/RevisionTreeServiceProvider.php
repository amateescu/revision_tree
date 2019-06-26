<?php

namespace Drupal\revision_tree;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class RevisionTreeServiceProvider extends ServiceProviderBase {

  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('workspaces.association')) {
      $definition = $container->getDefinition('workspaces.association');
      $definition->setClass(RevisionTreeWorkspaceAssociation::class);
    }
  }

}
