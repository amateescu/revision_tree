<?php

namespace Drupal\Tests\revision_tree\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Tests\token\Kernel\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use Drupal\user\Entity\User;
use PDO;

class RevisionTreeQueryTest extends KernelTestBase {
  use WorkspaceTestTrait;
  use UserCreationTrait;

  public static $modules = [
    'workspaces',
    'entity_test',
    'revision_tree',
    'user',
    'system',
    'filter',
    'text',
  ];

  protected $entityManager;

  protected function setUp() {
    parent::setUp();
    $this->installSchema('system', ['key_value_expire', 'sequences']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('user');

    $this->initializeWorkspacesModule();

    $a = BaseFieldDefinition::create('string')
      ->setName('a')
      ->setRevisionable(TRUE)
      ->setProvider('entity_test_rev');

    $b = BaseFieldDefinition::create('string')
      ->setName('b')
      ->setRevisionable(TRUE)
      ->setProvider('entity_test_rev');

    $c = BaseFieldDefinition::create('string')
      ->setName('c')
      ->setRevisionable(TRUE)
      ->setProvider('entity_test_rev');

    $d = BaseFieldDefinition::create('string')
      ->setName('d')
      ->setRevisionable(TRUE)
      ->setProvider('entity_test_rev');

    $this->container->get('state')->set('entity_test_rev.additional_base_field_definitions', [
      'a' => $a,
      'b' => $b,
      'c' => $c,
      'd' => $d,
    ]);

    $entity_type = clone \Drupal::entityTypeManager()->getDefinition('entity_test_rev');
    $entity_type->set('contextual_fields', [
      'a' => ['weight' => 1, 'context' => '@test_context:a'],
      'b' => ['weight' => 1, 'context'=> '@test_context:b'],
      'c' => ['weight' => 2, 'context' => '@test_context:c', 'neutral' => 'neutral'],
      'd' => ['weight' => -10, 'context' => '@test_context:d'],
    ]);
    \Drupal::state()->set('entity_test_rev.entity_type', $entity_type);
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();

    $this->entityManager = $this->container->get('entity.manager');

    // Setup an anonymous user for our tests.
    User::create(array(
      'name' => '',
      'uid' => 0,
    ))->save();

    $this->installConfig(['system']);

    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * Test querying without any contexts.
   *
   * If there are no contexts on entities or queries, all revisions are returned
   * with a matching score of '0'.
   */
  public function testContextualTreeWithoutContexts() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    $query =  $revisionTreeQuery->getContextualTree($storage->getEntityType(), []);
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '1', 'score' => '0', 'parent' => null, 'merge_parent' => null],
      ['entity_id' => '1', 'revision_id' => '2', 'score' => '0', 'parent' => '1', 'merge_parent' => null],
    ], $result);
  }

  /**
   * Test querying with matching context.
   *
   * If there is a matching context, it includes the according revision and the
   * revisions score is set to the contexts' weight.
   */
  public function testContextualTreeWithMatchingContext() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    $query =  $revisionTreeQuery->getContextualTree($storage->getEntityType(), [
      'a' => 'x',
    ]);
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '1', 'score' => '0', 'parent' => null, 'merge_parent' => null],
      ['entity_id' => '1', 'revision_id' => '2', 'score' => '1', 'parent' => '1', 'merge_parent' => null],
    ], $result);
  }

  /**
   * Test querying with non-matching context.
   *
   * If there is a non-matching context, the according revision is pruned from
   * the result.
   */
  public function testContextualTreeWithNonMatchingContext() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    $query =  $revisionTreeQuery->getContextualTree($storage->getEntityType(), [
      'a' => 'y',
    ]);
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '1', 'score' => '0', 'parent' => null, 'merge_parent' => null],
    ], $result);
  }

  /**
   * Test querying with fallback values.
   *
   * If multiple context values are provided, the score is reduced from left
   * to right.
   */
  public function testContextualTreeWithFallbackContext() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $z = $storage->createRevision($x);
    $z->set('a', 'y');
    $z->save();

    $query =  $revisionTreeQuery->getContextualTree($storage->getEntityType(), [
      'a' => ['y', 'x', NULL],
    ]);

    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '1', 'score' => '0.98', 'parent' => null, 'merge_parent' => null],
      ['entity_id' => '1', 'revision_id' => '2', 'score' => '0.99', 'parent' => '1', 'merge_parent' => null],
      ['entity_id' => '1', 'revision_id' => '3', 'score' => '1', 'parent' => '1', 'merge_parent' => null],
    ], $result);
  }

  /**
   * Test querying for leaves without any contexts.
   *
   * If there are no contexts on entities or queries, all leaves are returned.
   */
  public function testContextualLeavesWithoutContexts() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    $y1 = $storage->createRevision($x);
    $y1->set('a', 'y');
    $y1->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $z = $storage->createRevision($y);
    $z->set('a', 'x');
    $z->save();

    $query =  $revisionTreeQuery->getContextualLeaves($storage->getEntityType(), []);
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '3', 'score' => '0', 'parent' => '1', 'merge_parent' => null],
      ['entity_id' => '1', 'revision_id' => '4', 'score' => '0', 'parent' => '2', 'merge_parent' => null],
    ], $result);
  }

  /**
   * Test querying for leaves with matching contexts.
   *
   * If there are contexts, only leaves with this context are included.
   */
  public function testContextualLeavesWithMatchingContext() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    $y1 = $storage->createRevision($x);
    $y1->set('a', 'y');
    $y1->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $z = $storage->createRevision($y);
    $z->set('a', 'x');
    $z->save();

    $query =  $revisionTreeQuery->getContextualLeaves($storage->getEntityType(), ['a' => ['y']]);
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '3', 'score' => '1', 'parent' => '1', 'merge_parent' => null],
    ], $result);
  }

  /**
   * Test querying for leaves with multiple matching contexts values.
   *
   * If there are contexts, only leaves with this context are included and
   * weighted accordingly.
   */
  public function testContextualLeavesWithMultipleMatchingContext() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    $y1 = $storage->createRevision($x);
    $y1->set('a', 'y');
    $y1->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $z = $storage->createRevision($y);
    $z->set('a', 'x');
    $z->save();

    $query =  $revisionTreeQuery->getContextualLeaves($storage->getEntityType(), ['a' => ['y', 'x', null]]);
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '3', 'score' => '1', 'parent' => '1', 'merge_parent' => null],
      ['entity_id' => '1', 'revision_id' => '4', 'score' => '0.99', 'parent' => '2', 'merge_parent' => null],
    ], $result);
  }

  /**
   * Test querying for leaves with non-matching contexts.
   *
   * If no leaves match, the root should be returned as a leaf, even though it
   * is not a leaf in context of the whole tree.
   */
  public function testContextualLeavesRootFallback() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    $y1 = $storage->createRevision($x);
    $y1->set('a', 'y');
    $y1->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $z = $storage->createRevision($y);
    $z->set('a', 'x');
    $z->save();

    $query =  $revisionTreeQuery->getContextualLeaves($storage->getEntityType(), ['a' => ['foo']]);
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '1', 'score' => '0', 'parent' => null, 'merge_parent' => null],
    ], $result);
  }

  /**
   * Test querying for active leaves without context.
   *
   * If there is no context, all leaves will have a matching score of 0, and the
   * latest revision will win.
   */
  public function testActiveLeavesWithoutContext() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    $y1 = $storage->createRevision($x);
    $y1->set('a', 'y');
    $y1->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $z = $storage->createRevision($y);
    $z->set('a', 'x');
    $z->save();

    $query =  $revisionTreeQuery->getActiveLeaves($storage->getEntityType(), []);
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '4'],
    ], $result);
  }

  /**
   * Test querying for active leaves with a matching context.
   *
   * If a revision matches a context, it should be picked as active with higher
   * precedence.
   */
  public function testActiveLeavesWithMatchingContext() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    $y1 = $storage->createRevision($x);
    $y1->set('a', 'y');
    $y1->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $z = $storage->createRevision($y);
    $z->set('a', 'x');
    $z->save();

    $query =  $revisionTreeQuery->getActiveLeaves($storage->getEntityType(), ['a' => 'y']);
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '3'],
    ], $result);
  }

  /**
   * Test querying for active leaves with a non-matching context.
   *
   * If the context doesn't match any revisions, it should fall back to the
   * root revision.
   */
  public function testActiveLeavesWithNonMatchingContext() {
    /** @var \Drupal\revision_tree\RevisionTreeQueryInterface $repository */
    $revisionTreeQuery = $this->container->get('revision_tree.query');

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->set('a', 'x');
    $y->save();

    $y1 = $storage->createRevision($x);
    $y1->set('a', 'y');
    $y1->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $z = $storage->createRevision($y);
    $z->set('a', 'x');
    $z->save();

    $query =  $revisionTreeQuery->getActiveLeaves($storage->getEntityType(), ['a' => 'foo']);
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals([
      ['entity_id' => '1', 'revision_id' => '1'],
    ], $result);
  }

}
