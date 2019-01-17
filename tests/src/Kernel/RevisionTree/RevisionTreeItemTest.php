<?php

namespace Drupal\Tests\revision_tree\Kernel\RevisionTree;

use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;

/**
 * Tests the revision_tree field type.
 *
 * @group entity
 * @group revision_tree
 */
class RevisionTreeItemTest extends FieldKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['revision_tree'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');
  }

  /**
   * @coversDefaultClass \Drupal\revision_tree\Plugin\Field\FieldType\RevisionTreeItem
   */
  public function testRevisionTreeItem() {
    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_rev');

    // Check that the 'revision_parent' field is available by default for a
    // revisionable entity type.
    $base_field_definitions = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions('entity_test_rev');
    $this->assertArrayHasKey('revision_parent', $base_field_definitions);

    // Check that the first revision of an entity does not have a parent.
    $revision_1 = EntityTestRev::create([]);
    $this->assertNull($revision_1->revision_parent->target_id);
    $revision_1->save();

    // Check that the revision parent field is populated automatically for
    // subsequent revisions.
    $revision_2 = $storage->createRevision($revision_1, TRUE);
    $this->assertEquals($revision_1->getRevisionId(), $revision_2->revision_parent->target_id);
    $revision_2->save();

    $revision_3 = $storage->createRevision($revision_2, FALSE);
    $this->assertEquals($revision_2->getRevisionId(), $revision_3->revision_parent->target_id);
    $revision_3->save();

    // Check that a new revision that doesn't start from the latest one gets the
    // proper revision parent ID.
    $revision_4 = $storage->createRevision($revision_2, FALSE);
    $this->assertEquals($revision_2->getRevisionId(), $revision_4->revision_parent->target_id);

    // Check that we can assign the parent revision ID manually.
    $revision_5 = $storage->createRevision($revision_2, FALSE);
    $revision_5->revision_parent->target_id = $revision_1->getRevisionId();
    $this->assertEquals($revision_1->getRevisionId(), $revision_5->revision_parent->target_id);

    // Check that we can also assign the 'merge_target_id' value manually.
    $revision_6 = $storage->createRevision($revision_2, FALSE);
    $revision_6->revision_parent->target_id = $revision_2->getRevisionId();
    $revision_6->revision_parent->merge_target_id = $revision_3->getRevisionId();
    $this->assertEquals($revision_2->getRevisionId(), $revision_6->revision_parent->target_id);
    $this->assertEquals($revision_3->getRevisionId(), $revision_6->revision_parent->merge_target_id);
  }

  /**
   * @coversDefaultClass \Drupal\revision_tree\Plugin\Validation\Constraint\ValidRevisionTreeReferenceConstraintValidator
   */
  public function testRevisionTreeValidation() {
    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_rev');

    // Create an initial revision.
    /** @var \Drupal\entity_test\Entity\EntityTestRev $revision_1 */
    $revision_1 = $storage->create([]);
    $violations = $revision_1->revision_parent->validate();
    $this->assertEmpty($violations);
    $revision_1->save();

    // Create a few more revisions which should have their parents assigned
    // automatically.
    $revision_2 = $storage->createRevision($revision_1);
    $violations = $revision_2->revision_parent->validate();
    $this->assertEmpty($violations);
    $revision_2->save();

    $revision_3 = $storage->createRevision($revision_2);
    $violations = $revision_3->revision_parent->validate();
    $this->assertEmpty($violations);
    $revision_3->save();

    $revision_4 = $storage->createRevision($revision_1);
    $revision_4->revision_parent->merge_target_id = $revision_3->getRevisionId();
    $violations = $revision_4->revision_parent->validate();
    $this->assertEmpty($violations);
    $revision_4->save();

    // Try to change the parent of an existing revision.
    $revision_3->revision_parent->target_id = $revision_4->getRevisionId();
    $violations = $revision_3->revision_parent->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('The parent revision can not be changed for an existing revision.', $violations->get(0)->getMessage());

    // Try to change the merge parent of an existing revision.
    $revision_4->revision_parent->merge_target_id = $revision_2->getRevisionId();
    $violations = $revision_4->revision_parent->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('The parent revision can not be changed for an existing revision.', $violations->get(0)->getMessage());

    // Try to use the same revision ID for both the parent and the merge parent.
    $revision_5 = $storage->createRevision($revision_3);
    $revision_5->revision_parent->target_id = $revision_3->getRevisionId();
    $revision_5->revision_parent->merge_target_id = $revision_3->getRevisionId();
    $violations = $revision_5->revision_parent->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('The parent revision can not be the same as the merge revision.', $violations->get(0)->getMessage());

    // Try to use a parent that doesn't exist.
    $revision_6 = $storage->createRevision($revision_4);
    $revision_6->revision_parent->target_id = PHP_INT_MAX - 2;
    $revision_6->revision_parent->merge_target_id = PHP_INT_MAX - 1;
    $violations = $revision_6->revision_parent->validate();
    $this->assertCount(2, $violations);
    $this->assertEquals('This revision (<em class="placeholder">' . (PHP_INT_MAX - 2) . '</em>) does not exist.', $violations->get(0)->getMessage());
    $this->assertEquals('This revision (<em class="placeholder">' . (PHP_INT_MAX - 1) . '</em>) does not exist.', $violations->get(1)->getMessage());
  }

}
