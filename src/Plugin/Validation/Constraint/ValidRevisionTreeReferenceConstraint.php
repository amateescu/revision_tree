<?php

namespace Drupal\revision_tree\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Revision tree valid reference constraint.
 *
 * Verifies that referenced revisions are valid for the revision tree.
 *
 * @Constraint(
 *   id = "ValidRevisionTreeReference",
 *   label = @Translation("Valid parent revision reference", context = "Validation")
 * )
 */
class ValidRevisionTreeReferenceConstraint extends Constraint {

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
  public $readOnlyMessage = 'The parent revision can not be changed for an existing revision.';

  /**
   * Violation message when the parent and the merge revision are the same.
   *
   * @var string
   */
  public $sameRevisionMessage = 'The parent revision can not be the same as the merge revision.';

}
