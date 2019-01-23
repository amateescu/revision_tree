<?php

namespace Drupal\revision_tree\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\revision_tree\ConflictResolver\ConflictResolverManagerInterface;
use Drupal\revision_tree\ConflictResolverUI\ConflictResolverUIManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller class for manually resolving conflicts.
 */
class ConflictsResolver extends ControllerBase {


  /**
   * The conflict resolver manager.
   *
   * @var \Drupal\revision_tree\ConflictResolver\ConflictResolverManagerInterface
   */
  protected $conflictResolver;

  /**
   * The conflict resolver UI manager.
   *
   * @var \Drupal\revision_tree\ConflictResolverUI\ConflictResolverUIManagerInterface
   */
  protected $conflictResolverUI;

  /**
   * Constructs a ConflictsResolver object.
   * @param \Drupal\revision_tree\ConflictResolver\ConflictResolverManagerInterface $conflictResolver
   *  The conflict resolver manager service.
   */
  public function __construct(ConflictResolverManagerInterface $conflictResolver, ConflictResolverUIManagerInterface $conflictResolverUI) {
    $this->conflictResolver = $conflictResolver;
    $this->conflictResolverUI = $conflictResolverUI;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('revision_tree.conflict_resolver'),
      $container->get('revision_tree.conflict_resolver_ui')
    );
  }

  /**
   * Tries to automatically resolve a conflict. If not possible, it will return
   * a UI to manually resolve it.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $revision_a
   *  The first revision
   * @param \Drupal\Core\Entity\ContentEntityBase $revision_b
   *  The second revision.
   * @return array
   */
  public function resolve(ContentEntityBase $revision_a, ContentEntityBase $revision_b) {
    // @todo: find the LCA of revision_a and revision_b.
    $common_ancestor = $revision_b;
    // Check first if the two revisions are actually in conflict. If not, just
    // return a 404.
    if (!$this->conflictResolver->checkConflict($revision_a, $revision_b, $common_ancestor)) {
      throw new NotFoundHttpException();
    }

    // Try to automatically resolve the conflict. If succeeded
    $revision_c = $this->conflictResolver->resolveConflict($revision_a, $revision_b, $common_ancestor);
    if (!empty($revision_c) && $revision_c instanceof ContentEntityBase) {
      return [
        '#type' => 'item',
        '#markup' => 'Conflict resolved: ' . $revision_c->label()
      ];
    }

    // Finally, if the automatic conflict resolution didn't work, we'll just
    // show the UI to manually resolve the conflict. If there is no ui, we will
    // just return a 404.
    $ui = $this->conflictResolverUI->conflictResolverUI($revision_a, $revision_b);
    if (!empty($ui)) {
      return $ui;
    }
    throw new NotFoundHttpException();
  }
}
