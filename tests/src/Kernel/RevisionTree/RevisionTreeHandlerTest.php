<?php

namespace Drupal\Tests\revision_tree\Kernel\RevisionTree;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the revision_tree entity handler.
 *
 * @coversDefaultClass \Drupal\revision_tree\SqlRevisionTreeHandler
 *
 * @group entity
 * @group revision_tree
 */
class RevisionTreeHandlerTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['revision_tree', 'workspaces'];

  /**
   * The storage of the test entity type.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $storage;

  /**
   * The revision tree handler of the test entity type.
   *
   * @var \Drupal\revision_tree\RevisionTreeHandlerInterface
   */
  protected $revisionTreeHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');

    $this->storage = \Drupal::entityTypeManager()->getStorage('entity_test_rev');
    $this->revisionTreeHandler = \Drupal::entityTypeManager()->getHandler('entity_test_rev', 'revision_tree');
  }

  /**
   * @covers ::getLowestCommonAncestor
   */
  public function testGetLowestCommonAncestor() {
    // Create a complex revision graph:
    //
    //        3 -- 4 -- 5 -- 8
    //       /         /      \
    // 1 -- 2 -- 6 -- 7 ------ 9 --- 16
    //            \                 /
    //             10 -- 11 ------ 15
    //              \             /
    //               12 -- 13 -- 14
    //
    $revisions_graph = [
      1 => [NULL, NULL],
      2 => [1, NULL],
      3 => [2, NULL],
      4 => [3, NULL],
      5 => [4, 7],
      6 => [2, NULL],
      7 => [6, NULL],
      8 => [5, NULL],
      9 => [8, 7],
      10 => [6, NULL],
      11 => [10, NULL],
      12 => [10, NULL],
      13 => [12, NULL],
      14 => [13, NULL],
      15 => [11, 14],
      16 => [9, 15],
    ];
    $entity = $this->storage->create(['revision_id' => 1]);
    $entity->save();
    foreach ($revisions_graph as $revision_id => $parents) {
      $this->createRevision($entity, $revision_id, $parents[0], $parents[1]);
    }

    // And test the following cases for finding the lowest common ancestor.
    $test_cases = [
      '1 - 2' => 1,
      '2 - 3' => 2,
      '2 - 4' => 2,
      '6 - 4' => 2,
      '6 - 5' => 6,
      '7 - 5' => 7,
      '7 - 8' => 7,
      '7 - 9' => 7,
      '16 - 8' => 8,
      '10 - 7' => 6,
      '11 - 7' => 6,
      '13 - 4' => 2,
      '13 - 5' => 6,
      '13 - 9' => 6,
      '13 - 11' => 10,
      '13 - 15' => 13,
      '14 - 16' => 14,
      '14 - 8' => 6,
      '15 - 8' => 6,
      '15 - 9' => 6,
      '15 - 4' => 2,
      '15 - 5' => 6,
      '16 - 5' => 5,
    ];
    foreach ($test_cases as $key => $expected_lca) {
      list($revision_a, $revision_b) = explode(' - ', $key);
      $lca = $this->revisionTreeHandler->getLowestCommonAncestor($entity, $revision_a, $revision_b);

      $this->assertEquals($expected_lca, $lca);
    }
  }

  /**
   * Creates a revision for the test entity type.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revision entity object.
   * @param int $revision_id
   *   The ID of the revision that will be created.
   * @param int $parent_revision_id
   *   The parent ID of the revision that will be created.
   * @param int $merge_revision_id
   *   The merge parent ID of the revision that will be created.
   */
  protected function createRevision(RevisionableInterface $entity, $revision_id, $parent_revision_id, $merge_revision_id) {
    $revision = $this->storage->createRevision($entity);
    $revision->revision_id->value = $revision_id;
    $revision->revision_parent->target_id = $parent_revision_id;
    $revision->revision_parent->merge_target_id = $merge_revision_id;
    $revision->save();
  }

}
