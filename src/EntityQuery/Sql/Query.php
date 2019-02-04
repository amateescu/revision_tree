<?php

namespace Drupal\revision_tree\EntityQuery\Sql;

use Drupal\Core\Entity\Query\Sql\Query as BaseQuery;

/**
 * Extended entity query that allows to sort by contexts and query active revisions.
 */
class Query extends BaseQuery {

  /**
   * The list of sort context values, keyed by field name.
   *
   * @var array
   */
  protected $matchingContexts = [];

  /**
   * @var bool
   */
  protected $activeRevisions = false;

  /**
   * Retrieve the active revisions for each result entity.
   *
   * @param array $contexts
   *   The list of target context values keyed by field name.
   *
   * @return \Drupal\revision_tree\EntityQuery\Sql\Query
   *   The current query object.
   */
  public function activeRevisions(array $contexts) {
    $this->allRevisions();
    $this->activeRevisions = TRUE;
    $this->matchingContexts = $contexts;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function finish() {
    if (!$this->activeRevisions) {
      return parent::finish();
    }

    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $revisionTreeQuery */
    $revisionTreeQuery = \Drupal::service('revision_tree.query');

    $revisionField = $this->entityType->getKey('revision');

    $activeRevisions = $revisionTreeQuery->getActiveLeaves($this->entityType, $this->matchingContexts);
    $this->sqlQuery->innerJoin($activeRevisions, 'active', "base_table.$revisionField = active.revision_id");
    return parent::finish();
  }

}
