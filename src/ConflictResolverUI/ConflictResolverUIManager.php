<?php

namespace Drupal\revision_tree\ConflictResolverUI;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * The conflict resolver manager service.
 */
class ConflictResolverUIManager implements ConflictResolverUIManagerInterface {

  /**
   * An unsorted array of arrays of conflict resolver UI widgets.
   *
   * @var \Drupal\revision_tree\ConflictResolverUI\ConflictResolverUIInterface[][]
   */
  protected $conflictResolvers = [];

  /**
   * An array of conflict resolver UI, sorted by priority.
   *
   * If this is NULL a rebuild will be triggered.
   *
   * @var null|\Drupal\revision_tree\ConflictResolverUI\ConflictResolverUIInterface[]
   */
  protected $sortedConflictResolvers = NULL;


  /**
   * {@inheritdoc}
   */
  public function render(ContentEntityBase $revisionA, ContentEntityBase $revisionB) {
    if ($this->sortedConflictResolvers === NULL) {
      $this->sortedConflictResolvers = $this->sortConflictResolvers();
    }
    foreach ($this->sortedConflictResolvers as $conflictResolver) {
      if ($conflictResolver->applies($revisionA, $revisionB)) {
        return $conflictResolver->render($revisionA, $revisionB);
      }
    }
    // No conflict resolver UI widget was found, so just return null.
    return NULL;
  }

  /**
   * Appends a conflict resolver UI widget to the chain.
   *
   * @param \Drupal\revision_tree\ConflictResolverUI\ConflictResolverUIInterface $conflictResolver
   *   The conflict resolver to be appended.
   * @param int $priority
   *   The priority of the conflict resolver being added.
   * @return $this
   */
  public function addConflictResolverUI(ConflictResolverUIInterface $conflictResolver, $priority = 0) {
    $this->conflictResolvers[$priority][] = $conflictResolver;
    // Reset sorted conflict resolvers property to trigger rebuild.
    $this->sortedConflictResolvers = NULL;
    return $this;
  }

  /**
   * Sorts the conflict resolver UI widgets according to priority.
   *
   * @return \Drupal\revision_tree\ConflictResolverUI\ConflictResolverUIInterface[]
   *   A sorted array of conflict resolver UI widgets.
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
