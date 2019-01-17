<?php

namespace Drupal\revision_tree\EntityQuery\Sql;

use Drupal\Core\Entity\Query\Sql\QueryFactory as BaseQueryFactory;

/**
 * Workspaces-specific entity query implementation.
 */
class QueryFactory extends BaseQueryFactory {
  // No implementation. Just needs to register the namespace to load local
  // Query implementation.
}
