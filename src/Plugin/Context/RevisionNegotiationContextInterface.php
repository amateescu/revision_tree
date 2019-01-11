<?php

namespace Drupal\revision_tree\Plugin\Context;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\Context\ContextInterface;

/**
 * Interface for revision negotiation contexts.
 *
 * Revision negotiation contexts expose values that can be attached to entities
 * or entity revisions to identify the context they were created in.
 *
 * For example:
 *   workspace: stage
 *   user: 123
 *   langcode: german
 *
 * This information is used to determine the safest revision to edit or display.
 *
 * The context value is supposed to be stored in a dedicated entity field that
 * has to be created and populated by the providing module.
 */
interface RevisionNegotiationContextInterface extends ContextInterface {

  /**
   * Check if this context is applicable on a given entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type in question.
   *
   * @return boolean
   *   TRUE, if the context is applicable on this type.
   */
  public function applies(EntityTypeInterface $entityType);

  /**
   * The name of the entity field storing the context value.
   *
   * @return string
   *   The context field name.
   */
  public function getContextField();

  /**
   * Retrieve the context weight.
   *
   * Used when determining the best matching revision. The higher the weight,
   * the higher the influence of a given context in the decision. A negative
   * weight triggers an inversion, which means that a non-match of a context
   * will be weighed in to a certain extent. This can be used to scope revisions
   * e.g. within a workspace or for a given user.
   *
   * @return integer
   *   The signed integer weight value.
   */
  public function getWeight();
}
