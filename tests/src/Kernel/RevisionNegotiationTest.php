<?php

namespace Drupal\Tests\revision_tree\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\Entity\User;

class RevisionNegotiationTest extends EntityKernelTestBase {

  public static $modules = [
    'entity_test',
    'revision_tree',
    'user',
    'system',
  ];


  protected function setUp() {
    parent::setUp();

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
      ->setName('c')
      ->setRevisionable(TRUE)
      ->setProvider('entity_test_rev');

    $this->state->set('entity_test_rev.additional_base_field_definitions', [
      'a' => $a,
      'b' => $b,
      'c' => $c,
      'd' => $d,
    ]);

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('user');

    $entity_type = clone \Drupal::entityTypeManager()->getDefinition('entity_test_rev');
    $entity_type->set('entity_contexts', [
      'a' => 1,
      'b' => 1,
      'c' => 2,
      'd' => -10,
    ]);
    \Drupal::state()->set('entity_test_rev.entity_type', $entity_type);
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();

    // Setup an anonymous user for our tests.
    $anonymous = User::create(array(
      'name' => '',
      'uid' => 0,
    ));
    $anonymous
      ->save();

    $this->installConfig(['system']);

    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * A simple non-revisionable entity should return itself as the active revision.
   */
  public function testUnrevisionedActiveRevision() {
    $storage = $this->entityManager->getStorage('entity_test');
    $entity = $storage->create();
    $entity->save();

    /** @var \Drupal\revision_tree\Entity\EntityRepository $repository */
    $repository = $this->container->get('entity.repository');
    $this->assertEquals($entity, $repository->getActive($entity, ['foo' => 'bar']));
  }

  /**
   * If only one revision matches a context, this revision wins.
   */
  public function testContextMatching() {
    /** @var \Drupal\revision_tree\Entity\EntityRepository $repository */
    $repository = $this->container->get('entity.repository');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $a */
    $x = $storage->create();
    $x->a = 'x';
    $x->save();

    $y = $storage->createRevision($x);
    $y->a = 'y';
    $y->save();

    $this->assertEquals($x->getLoadedRevisionId(), $repository->getActive($x, ['a' => 'x'])->getLoadedRevisionId());
    $this->assertEquals($y->getLoadedRevisionId(), $repository->getActive($x, ['a' => 'y'])->getLoadedRevisionId());
  }

  /**
   * If multiple revisions match, the higher context weight sum wins.
   */
  public function testContextOrdering() {
    /** @var \Drupal\revision_tree\Entity\EntityRepository $repository */
    $repository = $this->container->get('entity.repository');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $a */
    $x = $storage->create();
    $x->a = 'x';
    $x->c = 'foo';
    $x->save();

    $y = $storage->createRevision($x);
    $y->a = 'foo';
    $y->c = 'y';
    $y->save();

    $this->assertEquals($y->getLoadedRevisionId(), $repository->getActive($x, ['a' => 'x', 'c' => 'y'])->getLoadedRevisionId());
  }

  /**
   * A negative context weight triggers an inverse match.
   * Non-matching revisions are ranked down instead of matching revisions up.
   */
  public function testNegativeMatching() {
    /** @var \Drupal\revision_tree\Entity\EntityRepository $repository */
    $repository = $this->container->get('entity.repository');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $a */
    $x = $storage->create();
    $x->a = 'x';
    $x->c = 'y';
    $x->d = 'foo';
    $x->save();

    $y = $storage->createRevision($x);
    $y->a = 'foo';
    $y->c = 'foo';
    $y->d = 'z';
    $y->save();

    $this->assertEquals($y->getLoadedRevisionId(), $repository->getActive($x, ['a' => 'x', 'c' => 'y', 'd' => 'z'])->getLoadedRevisionId());
  }

  /**
   * When passing an array as the context value, this will
   */
  public function testFallbackMatching() {
    /** @var \Drupal\revision_tree\Entity\EntityRepository $repository */
    $repository = $this->container->get('entity.repository');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $a */
    $x = $storage->create();
    $x->a = 'foo';
    $x->c = 'z';
    $x->save();

    $y = $storage->createRevision($x);
    $y->a = 'x';
    $y->c = 'foo';
    $y->save();

    $this->assertEquals($x->getLoadedRevisionId(), $repository->getActive($x, ['a' => 'x', 'c' => ['y', 'z'] ])->getLoadedRevisionId());

  }

  public function testActiveRevisionsQuery() {
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $a */
    $x = $storage->create();
    $x->a = 'x';
    $x->save();

    $y = $storage->createRevision($x);
    $y->a = 'y';
    $y->save();


    /** @var \Drupal\revision_tree\EntityQuery\Query $query */
    $query = $storage->getQuery();
    $query->activeRevisions(['a' => 'y']);
    $result = $query->execute();
    $this->assertEquals([2  => '1'], $result);
  }
}
