<?php

namespace Drupal\revision_tree\ConflictResolver;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * The conflict resolver manager service.
 */
class ConflictResolverManager implements ConflictResolverManagerInterface {

  /**
   * Holds an array of conflict resolver service IDs, sorted by priority.
   *
   * @var string[]
   */
  protected $conflictResolverIds = [];

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Constructs a new ConflictResolverManager.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param string[] $conflict_resolver_ids
   *   An array of conflict resolver service IDs.
   */
  public function __construct(ClassResolverInterface $class_resolver, array $conflict_resolver_ids) {
    $this->classResolver = $class_resolver;
    $this->conflictResolverIds = $conflict_resolver_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function checkConflict(RevisionableInterface $revision_a, RevisionableInterface $revision_b) {
    // @todo Write an actual conflict checker.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflict(RevisionableInterface $revision_a, RevisionableInterface $revision_b, RevisionableInterface $common_ancestor) {
    foreach ($this->conflictResolverIds as $conflict_resolver_id) {
      $conflict_resolver = $this->classResolver->getInstanceFromDefinition($conflict_resolver_id);

      if ($conflict_resolver->applies($revision_a, $revision_b)) {
        if ($revision = $conflict_resolver->resolveConflict($revision_a, $revision_b, $common_ancestor)) {
          return $revision;
        }
      }
    }

    // No conflict resolver was able to resolve the conflict.
    return NULL;
  }

}
