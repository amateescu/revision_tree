<?php

namespace Drupal\revision_tree;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\revision_tree\Entity\EntityRepository;
use Drupal\revision_tree\ParamConverter\RevisionTreeEntityConverter;
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
  }

}
