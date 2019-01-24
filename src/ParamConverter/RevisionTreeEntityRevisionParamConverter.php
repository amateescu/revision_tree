<?php

namespace Drupal\revision_tree\ParamConverter;

use Drupal\Core\ParamConverter\EntityRevisionParamConverter;
use Drupal\Core\ParamConverter\ParamNotConvertedException;

/**
 * Replacement for the entity revision param converter service until
 * https://www.drupal.org/project/drupal/issues/2808163 gets into core (Support
 * dynamic Entity Types in the EntityRevisionParamConverter).
 */
class RevisionTreeEntityRevisionParamConverter extends EntityRevisionParamConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->loadRevision($value);
    return $this->entityRepository->getTranslationFromContext($entity);
  }

  /**
   * Determines the entity type ID given a route definition and route defaults.
   *
   * @param mixed $definition
   *   The parameter definition provided in the route options.
   * @param string $name
   *   The name of the parameter.
   * @param array $defaults
   *   The route defaults array.
   *
   * @return string
   *   The entity type ID.
   *
   * @throws \Drupal\Core\ParamConverter\ParamNotConvertedException
   *   Thrown when the dynamic entity type is not found in the route defaults.
   */
  protected function getEntityTypeFromDefaults($definition, $name, array $defaults) {
    $entity_type_id = substr($definition['type'], strlen('entity_revision:'));

    // If the entity type is dynamic, it will be pulled from the route defaults.
    if (strpos($entity_type_id, '{') === 0) {
      $entity_type_slug = substr($entity_type_id, 1, -1);
      if (!isset($defaults[$entity_type_slug])) {
        throw new ParamNotConvertedException(sprintf('The "%s" parameter was not converted because the "%s" parameter is missing', $name, $entity_type_slug));
      }
      $entity_type_id = $defaults[$entity_type_slug];
    }
    return $entity_type_id;
  }

}
