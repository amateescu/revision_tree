<?php

namespace Drupal\revision_tree;

use Drupal\Core\Entity\EntityInterface;
use Drupal\workspaces\WorkspaceListBuilder;

class RevisionTreeWorkspaceListBuilder extends WorkspaceListBuilder {

  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if (!$entity->isDefaultWorkspace()) {
      $operations['associations'] = [
        'title' => $this->t('Rebuild associations'),
        'url' => $entity->toUrl('associations-form', ['query' => ['destination' => $entity->toUrl('collection')->toString()]]),
      ];
    }
    return $operations;
  }

}
