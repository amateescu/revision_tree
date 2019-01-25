<?php

namespace Drupal\revision_tree\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\revision_tree\ConflictResolver\ConflictResolverManagerInterface;
use Drupal\revision_tree\ConflictResolverUI\ConflictResolverUIManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a ConflictsResolver object.
   *
   * @param \Drupal\revision_tree\ConflictResolver\ConflictResolverManagerInterface $conflictResolver
   *   The conflict resolver manager service.
   */
  public function __construct(ConflictResolverManagerInterface $conflictResolver, ConflictResolverUIManagerInterface $conflictResolverUI, EntityRepositoryInterface $entityRepository) {
    $this->conflictResolver = $conflictResolver;
    $this->conflictResolverUI = $conflictResolverUI;
    $this->entityRepository = $entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('revision_tree.conflict_resolver'),
      $container->get('revision_tree.conflict_resolver_ui'),
      $container->get('entity.repository')
    );
  }

  /**
   * Tries to automatically resolve a conflict. If not possible, it will return
   * a UI to manually resolve it.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_a
   *   The first revision
   * @param \Drupal\Core\Entity\RevisionableInterface $revision_b
   *   The second revision.
   *
   * @return array
   *   A render array.
   */
  public function resolve(RevisionableInterface $revision_a, RevisionableInterface $revision_b) {
    $common_ancestor = $this->getLowestCommonAncestorEntity($revision_a, $revision_a->getRevisionId(), $revision_b->getRevisionId());
    // Check first if the two revisions are actually in conflict. If not, just
    // return a 404.
    if (!$this->conflictResolver->checkConflict($revision_a, $revision_b)) {
      throw new NotFoundHttpException();
    }

    // Try to automatically resolve the conflict. If succeeded, then redirect
    // the user to the edit form of that entity type.
    $revision_c = $this->conflictResolver->resolveConflict($revision_a, $revision_b, $common_ancestor);
    if (!empty($revision_c) && $revision_c instanceof RevisionableInterface) {
      $this->messenger()->addMessage($this->t('The conflict was automatically resolved. Bellow you have a preview of it.'));
      return new RedirectResponse($revision_c->toUrl('revision')->toString());
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

  /**
   * Returns the lowest common ancestor entity revision of two revisions.
   */
  protected function getLowestCommonAncestorEntity(RevisionableInterface $entity, $first_revision_id, $second_revision_id) {
    /* @var \Drupal\revision_tree\RevisionTreeHandlerInterface $revisionTreeHandler */
    $revisionTreeHandler = $this->entityTypeManager()->getHandler($entity->getEntityTypeId(), 'revision_tree');
    $commonAncestor = $revisionTreeHandler->getLowestCommonAncestor($entity, $first_revision_id, $second_revision_id);
    if (!empty($commonAncestor)) {
      $commonAncestor = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadRevision($commonAncestor);
      if ($commonAncestor instanceof TranslatableInterface) {
        $commonAncestor = $this->entityRepository->getTranslationFromContext($commonAncestor);
      }
      return $commonAncestor;
    }
    return NULL;
  }

}
