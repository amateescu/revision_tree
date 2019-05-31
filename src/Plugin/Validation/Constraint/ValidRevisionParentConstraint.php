<?php

namespace Drupal\revision_tree\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Verifies that referenced revisions parents are valid.
 *
 * @Constraint(
 *   id = "ValidRevisionParent",
 *   label = @Translation("Valid revision parent", context = "Validation"),
 *   type = { "entity" },
 * )
 */
class ValidRevisionParentConstraint extends Constraint {

  /**
   * Violation message when the referenced revisions does not exist.
   *
   * @var string
   */
  public $message = 'This revision (%revision_id) does not exist.';

  /**
   * Violation message when the parent of an existing revision is changed.
   *
   * @var string
   */
  public $readOnlyMessage = 'The revision parents can not be changed for an existing revision.';

  /**
   * Violation message when the parent and the merge revision are the same.
   *
   * @var string
   */
  public $sameRevisionMessage = 'The revision parents can not be the same.';

}
