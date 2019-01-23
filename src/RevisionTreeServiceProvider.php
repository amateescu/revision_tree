<?php

namespace Drupal\revision_tree;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\revision_tree\Entity\EntityRepository;
use Drupal\revision_tree\ParamConverter\RevisionTreeEntityConverter;
use Drupal\revision_tree\ParamConverter\RevisionTreeEntityRevisionParamConverter;
use Symfony\Component\DependencyInjection\Reference;

class RevisionTreeServiceProvider extends ServiceProviderBase {

  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('entity.repository')) {
      $definition = $container->getDefinition('entity.repository');
      $definition->setClass(EntityRepository::class);
      $definition->addArgument(new Reference('context.repository'));
    }

    if ($container->hasDefinition('paramconverter.entity')) {
      $definition = $container->getDefinition('paramconverter.entity');
      $definition->setClass(RevisionTreeEntityConverter::class);
      $definition->addArgument(new Reference('entity.repository'));
    }

    if ($container->hasDefinition('paramconverter.latest_revision')) {
      $definition = $container->getDefinition('paramconverter.latest_revision');
      $definition->setClass(RevisionTreeEntityConverter::class);
    }

   //Fixes a blacklist matching bug in the original WorkspaceManager.
   // TODO: Move this to a patch.
    if ($container->hasDefinition('workspaces.manager')) {
      $definition = $container->getDefinition('workspaces.manager');
      $definition->setClass(WorkspaceManager::class);
    }

    // Replace the entity revision param converter service until
    // https://www.drupal.org/project/drupal/issues/2808163 gets into core.
    if ($container->hasDefinition('paramconverter.entity_revision')) {
      $definition = $container->getDefinition('paramconverter.entity_revision');
      $definition->setClass(RevisionTreeEntityRevisionParamConverter::class);
    }

  }

}
