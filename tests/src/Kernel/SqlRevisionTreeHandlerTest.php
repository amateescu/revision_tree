<?php

namespace Drupal\Tests\revision_tree\Kernel;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the revision_tree entity handler.
 *
 * @coversDefaultClass \Drupal\revision_tree\SqlRevisionTreeHandler
 *
 * @group Entity
 * @group revision_tree
 */
class SqlRevisionTreeHandlerTest extends EntityKernelTestBase {

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
   * @var \Drupal\revision_tree\EntityRevisionTreeHandlerInterface
   */
  protected $revisionTree;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');

    $this->storage = \Drupal::entityTypeManager()->getStorage('entity_test_rev');
    $this->revisionTree = \Drupal::entityTypeManager()->getHandler('entity_test_rev', 'revision_tree');
  }

  /**
   * @covers ::getParentRevisionId
   * @covers ::getMergeParentRevisionId
   * @covers ::setParentRevisionId
   * @covers ::setMergeParentRevisionId
   */
  public function testRevisionParents() {
    $entity_field_manager = \Drupal::service('entity_field.manager');

    // Check that the 'revision_parent' field is available by default for a
    // revisionable entity type.
    $base_field_definitions = $entity_field_manager->getBaseFieldDefinitions('entity_test_mulrev');
    $this->assertArrayHasKey('revision_parent', $base_field_definitions);

    // Check that the 'revision_parent' field is not available for a
    // non-revisionable entity type
    $base_field_definitions = $entity_field_manager->getBaseFieldDefinitions('entity_test_mul');
    $this->assertArrayNotHasKey('revision_parent', $base_field_definitions);

    // Check that the first revision of an entity does not have a parent.
    /** @var \Drupal\Core\Entity\RevisionableInterface $revision_1 */
    $revision_1 = $this->storage->create();
    $revision_1->save();
    $this->assertEmpty($this->revisionTree->getParentRevisionId($revision_1));
    $this->assertEmpty($this->revisionTree->getMergeParentRevisionId($revision_1));

    // Check that the revision parent field is populated automatically for
    // subsequent revisions.
    $revision_2 = $this->storage->createRevision($revision_1, TRUE);
    $revision_2->save();
    $this->assertEquals($revision_1->getRevisionId(), $this->revisionTree->getParentRevisionId($revision_2));
    $this->assertEmpty($this->revisionTree->getMergeParentRevisionId($revision_2));

    $revision_3 = $this->storage->createRevision($revision_2, FALSE);
    $revision_3->save();
    $this->assertEquals($revision_2->getRevisionId(), $this->revisionTree->getParentRevisionId($revision_3));
    $this->assertEmpty($this->revisionTree->getMergeParentRevisionId($revision_3));

    // Check that a new revision that doesn't start from the latest one gets the
    // proper revision parent ID, which is the active revision of the branch.
    $revision_4 = $this->storage->createRevision($revision_2, FALSE);
    $revision_4->save();
    $this->assertEquals($revision_3->getRevisionId(), $this->revisionTree->getParentRevisionId($revision_4));
    $this->assertEmpty($this->revisionTree->getMergeParentRevisionId($revision_4));

    // Check that we can assign the parent revision IDs manually.
    $revision_5 = $this->storage->createRevision($revision_4, FALSE);
    $this->revisionTree->setParentRevisionId($revision_5, $revision_2->getRevisionId());
    $revision_5->save();
    $this->assertEquals($revision_2->getRevisionId(), $this->revisionTree->getParentRevisionId($revision_5));
    $this->assertEmpty($this->revisionTree->getMergeParentRevisionId($revision_5));

    $revision_6 = $this->storage->createRevision($revision_5, FALSE);
    $this->revisionTree->setParentRevisionId($revision_6, $revision_2->getRevisionId());
    $this->revisionTree->setMergeParentRevisionId($revision_6, $revision_3->getRevisionId());
    $revision_6->save();
    $this->assertEquals($revision_2->getRevisionId(), $this->revisionTree->getParentRevisionId($revision_6));
    $this->assertEquals($revision_3->getRevisionId(), $this->revisionTree->getMergeParentRevisionId($revision_6));
  }

  /**
   * @coversDefaultClass \Drupal\Core\Entity\Plugin\Validation\Constraint\ValidRevisionParentConstraintValidator
   */
  public function testRevisionParentsValidation() {
    \Drupal::currentUser()->setAccount($this->createUser());

    // Create an initial revision.
    /** @var \Drupal\Core\Entity\RevisionableInterface $revision_1 */
    $revision_1 = $this->storage->create();
    $violations = $revision_1->validate();
    $this->assertEmpty($violations);
    $revision_1->save();

    // Create a few more revisions which should have their parents assigned
    // automatically.
    $revision_2 = $this->storage->createRevision($revision_1);
    $violations = $revision_2->validate();
    $this->assertEmpty($violations);
    $revision_2->save();

    $revision_3 = $this->storage->createRevision($revision_2);
    $violations = $revision_3->validate();
    $this->assertEmpty($violations);
    $revision_3->save();

    $revision_4 = $this->storage->createRevision($revision_1);
    $this->revisionTree->setParentRevisionId($revision_4, $revision_1->getRevisionId());
    $this->revisionTree->setMergeParentRevisionId($revision_4, $revision_3->getRevisionId());
    $violations = $revision_4->validate();
    $this->assertEmpty($violations);
    $revision_4->save();

    // Try to change the parent of an existing revision.
    $this->revisionTree->setParentRevisionId($revision_3, $revision_4->getRevisionId());
    $violations = $revision_3->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('The revision parents can not be changed for an existing revision.', $violations->get(0)->getMessage());
    $this->assertEquals('revision_parent', $violations->get(0)->getPropertyPath());

    // Try to change the merge parent of an existing revision.
    $this->revisionTree->setMergeParentRevisionId($revision_4, $revision_2->getRevisionId());
    $violations = $revision_4->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('The revision parents can not be changed for an existing revision.', $violations->get(0)->getMessage());
    $this->assertEquals('revision_merge_parent', $violations->get(0)->getPropertyPath());

    // Try to use the same revision ID for both parents.
    $revision_5 = $this->storage->createRevision($revision_3);
    $this->revisionTree->setParentRevisionId($revision_5, $revision_3->getRevisionId());
    $this->revisionTree->setMergeParentRevisionId($revision_5, $revision_3->getRevisionId());
    $violations = $revision_5->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('The revision parents can not be the same.', $violations->get(0)->getMessage());
    $this->assertEquals('revision_parent', $violations->get(0)->getPropertyPath());

    // Try to use parents that don't exist.
    $revision_6 = $this->storage->createRevision($revision_1);
    $this->revisionTree->setParentRevisionId($revision_6, 500);
    $this->revisionTree->setMergeParentRevisionId($revision_6, 501);
    $violations = $revision_6->validate();
    $this->assertCount(2, $violations);
    $this->assertEquals('This revision (<em class="placeholder">500</em>) does not exist.', $violations->get(0)->getMessage());
    $this->assertEquals('This revision (<em class="placeholder">501</em>) does not exist.', $violations->get(1)->getMessage());
    $this->assertEquals('revision_parent', $violations->get(0)->getPropertyPath());
    $this->assertEquals('revision_merge_parent', $violations->get(1)->getPropertyPath());
  }

  /**
   * @covers ::getLowestCommonAncestorId
   */
  public function testGetLowestCommonAncestor() {
    // Create a complex revision graph:
    //
    //        3 -- 6 -- 7 -- 8
    //       /         /      \
    // 1 -- 2 -- 4 -- 5 ------ 9 --- 16
    //            \                 /
    //             10 -- 11 ------ 15
    //              \             /
    //               12 -- 13 -- 14
    //
    $revisions_graph = [
      1 => [NULL, NULL],
      2 => [1, NULL],
      3 => [2, NULL],
      4 => [2, NULL],
      5 => [4, NULL],
      6 => [3, NULL],
      7 => [6, 5],
      8 => [7, NULL],
      9 => [5, 8],
      10 => [4, NULL],
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
      '2 - 1' => 1,
      '2 - 3' => 2,
      '2 - 6' => 2,
      '4 - 6' => 2,
      '6 - 4' => 2,
      '7 - 5' => 5,
      '5 - 7' => 5,
      '7 - 9' => 7,
      '16 - 8' => 8,
      '10 - 6' => 2,
      '10 - 7' => 4,
      '11 - 7' => 4,
      '13 - 6' => 2,
      '13 - 5' => 4,
      '13 - 9' => 4,
      '13 - 11' => 10,
      '13 - 15' => 13,
      '14 - 16' => 14,
      '14 - 8' => 4,
      '15 - 8' => 4,
      '15 - 9' => 4,
      '15 - 6' => 2,
      '15 - 7' => 4,
      '16 - 5' => 5,
    ];
    foreach ($test_cases as $key => $expected_lca) {
      list($revision_a, $revision_b) = explode(' - ', $key);
      $lca = $this->revisionTree->getLowestCommonAncestorId($revision_a, $revision_b, $entity->id());

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
    $revision->set('revision_id', $revision_id);
    $this->revisionTree->setParentRevisionId($revision, $parent_revision_id);
    $this->revisionTree->setMergeParentRevisionId($revision, $merge_revision_id);
    $revision->save();
  }

}
