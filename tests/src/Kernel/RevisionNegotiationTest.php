<?php

namespace Drupal\Tests\revision_tree\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\Entity\User;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Definition;

class RevisionNegotiationTest extends EntityKernelTestBase {

  public static $modules = [
    'entity_test',
    'revision_tree',
    'user',
    'system',
  ];

  static $mockContextProvider;

  static $contextDefinitions;

  static function contextProviderFactory() {
    return static::$mockContextProvider->reveal();
  }

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
    $entity_type->set('contextual_fields', [
      'a' => ['weight' => 1, 'context' => '@test_context:a'],
      'b' => ['weight' => 1, 'context'=> '@test_context:b'],
      'c' => ['weight' => 2, 'context' => '@test_context:c'],
      'd' => ['weight' => -10, 'context' => '@test_context:d'],
    ]);
    \Drupal::state()->set('entity_test_rev.entity_type', $entity_type);
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();

    // Setup an anonymous user for our tests.
    User::create(array(
      'name' => '',
      'uid' => 0,
    ))->save();

    $this->installConfig(['system']);

    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * Register a mock context provider to inject context values.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    static::$contextDefinitions = array_map(function ($context) {
      return new ContextDefinition('string', strtoupper($context), FALSE);
    }, [
      'a' => 'a',
      'b' => 'b',
      'c' => 'c',
      'd' => 'd',
    ]);

    static::$mockContextProvider = $this->prophesize(ContextProviderInterface::class);
    static::$mockContextProvider->getAvailableContexts()->willReturn(array_map(function (ContextDefinition $definition) {
      return new Context($definition);
    }, static::$contextDefinitions));


    $definition = new Definition(get_class(static::$mockContextProvider));
    $definition->setFactory([RevisionNegotiationTest::class, 'contextProviderFactory']);
    $definition->addTag('context_provider');
    $container->setDefinition('test_context', $definition);
  }

  /**
   * Helper method to mock test context values.
   *
   * @param array $contexts
   *   Key value pair of context values.
   */
  protected function mockContexts($contexts = []) {
    $return = [];
    $contexts = $contexts + [
      'a' => NULL,
      'b' => NULL,
      'c' => NULL,
      'd' => NULL,
    ];

    foreach ($contexts as $id => $value) {
      $return[$id] = new Context(static::$contextDefinitions[$id], $value);
    }

    // Clear the lazy context repositories static cache.
    $repository = $this->container->get('context.repository');
    $reflection = new ReflectionClass($repository);
    $property = $reflection->getProperty('contexts');
    $property->setAccessible(TRUE);
    $property->setValue($repository, []);

    static::$mockContextProvider
      ->getRuntimeContexts(['a', 'b', 'c', 'd'])
      ->willReturn($return);
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

    $this->mockContexts(['a' => 'x']);
    $this->assertEquals($x->getLoadedRevisionId(), $repository->getActive($x)->getLoadedRevisionId());

    $this->mockContexts(['a' => 'y']);
    $this->assertEquals($y->getLoadedRevisionId(), $repository->getActive($x)->getLoadedRevisionId());
  }

  /**
   * Context values can be overridden by passing them to `getActive[Multiple]`.
   */
  public function testContextOverride() {
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

    $this->mockContexts(['a' => 'y']);
    $this->assertEquals($x->getLoadedRevisionId(), $repository->getActive($x, ['a' => 'x'])->getLoadedRevisionId());

    $this->mockContexts(['a' => 'x']);
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

    $this->mockContexts(['a' => 'x', 'c' => 'y']);
    $this->assertEquals($y->getLoadedRevisionId(), $repository->getActive($x)->getLoadedRevisionId());
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

    $this->mockContexts(['a' => 'x', 'c' => 'y', 'd' => 'z']);
    $this->assertEquals($y->getLoadedRevisionId(), $repository->getActive($x)->getLoadedRevisionId());
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

    $this->mockContexts(['a' => 'x', 'c' => ['y', 'z'] ]);
    $this->assertEquals($x->getLoadedRevisionId(), $repository->getActive($x)->getLoadedRevisionId());
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
