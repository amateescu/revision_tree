<?php

namespace Drupal\revision_tree\ParamConverter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\ParamConverter\EntityConverter;

/**
 * Overridden EntityConverter that tries to pull active revisions.
 */
class RevisionTreeEntityConverter extends EntityConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);


    // If the entity type is revisionable and the parameter has the
    // "load_latest_revision" flag, load the latest revision.
    if (!empty($definition['load_latest_revision']) && $entity_definition->isRevisionable()) {
      // Retrieve the latest revision ID taking translations into account.
      $langcode = $this->languageManager()
        ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
        ->getId();
      $entity = $this->entityRepository->getActive($entity_type_id, $value, ['langcode' => $langcode]);
    }
    else if ($entity_definition->isRevisionable()) {
      $langcode = $this->languageManager()
        ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
        ->getId();
      $entity = $this->entityRepository->getActive($entity_type_id, $value, ['langcode' => $langcode]);
    }
    else {
      $entity = $storage->load($value);
    }

    // If the entity type is translatable, ensure we return the proper
    // translation object for the current context.
    if ($entity instanceof EntityInterface && $entity instanceof TranslatableInterface) {
      $entity = $this->entityRepository->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
    }

    return $entity;
  }

}
