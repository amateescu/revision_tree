<?php

namespace Drupal\revision_tree;

use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Service for building query helpers that operate on the revision tree.
 */
interface RevisionTreeQueryInterface {

  /**
   * Retrieve a contextually pruned tree.
   *
   * Only contains revisions that are relevant for the given set of contexts.
   * Additionally these revisions receive a score that indicates how relevant
   * they are for a given context.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entityType
   *   The entity type, to retrieve table and meta information from.
   * @param array $contexts
   *   The list of context value options.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface | string
   *   A select query or table name that can be used in queries.
   *   The table will contain five columns:
   *   entity_id, revision_id, score, parent and merge_parent
   */
  public function getContextualTree(ContentEntityTypeInterface $entityType, array $contexts);

  /**
   * Retrieve the leaves of contextually pruned tree.
   *
   * Only contains revisions that are relevant for the given set of contexts and
   * that are not parent revisions within the current context.
   * Additionally these revisions receive a score that indicates how relevant
   * they are for a given context.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entityType
   *   The entity type, to retrieve table and meta information from.
   * @param array $contexts
   *   The list of context value options.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface | string
   *   A select query or table name that can be used in queries. Will contain
   *   at least three columns: entity_id, revision_id and score.
   */
  public function getContextualLeaves(ContentEntityTypeInterface $entityType, array $contexts);

  /**
   * Retrieve the most "fitting" leaves of contextually pruned tree.
   *
   * Reduces all tree leaves to the one most relevant for the current set of
   * contexts for each entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entityType
   *   The entity type, to retrieve table and meta information from.
   * @param array $contexts
   *   The list of context value options.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface | string
   *   A select query or table name that can be used in queries. Will contain
   *   at least two columns: entity_id, revision_id.
   */
  public function getActiveLeaves(ContentEntityTypeInterface $entityType, array $contexts);

}
