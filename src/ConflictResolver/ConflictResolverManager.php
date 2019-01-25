<?php

namespace Drupal\revision_tree\ConflictResolver;

use Drupal\Core\Entity\RevisionableInterface;

/**
 * The conflict resolver manager service.
 */
class ConflictResolverManager implements ConflictResolverManagerInterface {

  /**
   * An unsorted array of arrays of conflict resolvers.
   *
   * @var \Drupal\revision_tree\ConflictResolver\ConflictResolverInterface[][]
   */
  protected $conflictResolvers = [];

  /**
   * An array of conflict resolvers, sorted by priority.
   *
   * If this is NULL a rebuild will be triggered.
   *
   * @var \Drupal\revision_tree\ConflictResolver\ConflictResolverInterface[]|null
   */
  protected $sortedConflictResolvers = NULL;

  /**
   * {@inheritdoc}
   */
  public function checkConflict(RevisionableInterface $revision_a, RevisionableInterface $revision_b) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflict(RevisionableInterface $revision_a, RevisionableInterface $revision_b, RevisionableInterface $common_ancestor) {
    if ($this->sortedConflictResolvers === NULL) {
      $this->sortedConflictResolvers = $this->sortConflictResolvers();
    }
    foreach ($this->sortedConflictResolvers as $conflictResolver) {
      if ($conflictResolver->applies($revision_a, $revision_b)) {
        $revision = $conflictResolver->resolveConflict($revision_a, $revision_b, $common_ancestor);
        if (!is_null($revision) && $revision instanceof RevisionableInterface) {
          return $revision;
        }
      }
    }
    // No conflict resolver was able to automatically resolve the conflict.
    return NULL;
  }

  /**
   * Appends a conflict resolver to the chain.
   *
   * @param \Drupal\revision_tree\ConflictResolver\ConflictResolverInterface $conflictResolver
   *   The conflict resolver to be appended.
   * @param int $priority
   *   The priority of the conflict resolver being added.
   *
   * @return $this
   */
  public function addConflictResolver(ConflictResolverInterface $conflictResolver, $priority = 0) {
    $this->conflictResolvers[$priority][] = $conflictResolver;
    // Reset sorted conflict resolvers property to trigger rebuild.
    $this->sortedConflictResolvers = NULL;
    return $this;
  }

  /**
   * Sorts the conflict resolvers according to priority.
   *
   * @return \Drupal\revision_tree\ConflictResolver\ConflictResolverInterface[]
   *   A sorted array of conflict resolvers.
   */
  protected function sortConflictResolvers() {
    $sorted = [];
    krsort($this->conflictResolvers);

    foreach ($this->conflictResolvers as $conflictResolver) {
      $sorted = array_merge($sorted, $conflictResolver);
    }
    return $sorted;
  }

}
