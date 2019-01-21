<?php

namespace Drupal\revision_tree\ParamConverter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\revision_tree\Entity\RevisionTreeEntityRepositoryInterface;

/**
 * Overridden EntityConverter that tries to pull active revisions.
 */
class RevisionTreeEntityConverter extends EntityConverter {

  /**
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  public function __construct(
    EntityManagerInterface $entity_manager,
    LanguageManagerInterface $language_manager,
    RevisionTreeEntityRepositoryInterface $entity_repository
  ) {
    parent::__construct($entity_manager, $language_manager);
    $this->entityRepository = $entity_repository;
  }


  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    $storage = $this->entityManager->getStorage($entity_type_id);
    $entity_definition = $this->entityManager->getDefinition($entity_type_id);


    // If the entity type is revisionable and the parameter has the
    // "load_latest_revision" flag, load the latest revision.
    if (!empty($definition['load_latest_revision']) && $entity_definition->isRevisionable()) {
      // Retrieve the latest revision ID taking translations into account.
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
      $entity = $this->entityManager->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
    }

    return $entity;
  }

}
