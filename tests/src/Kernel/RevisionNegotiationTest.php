<?php

namespace Drupal\Tests\revision_tree\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\token\Kernel\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use Drupal\user\Entity\User;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Definition;

class RevisionNegotiationTest extends KernelTestBase {
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

  static $mockContextProvider;

  static $contextDefinitions;

  protected $entityManager;
  protected $state;

  static function contextProviderFactory() {
    return static::$mockContextProvider->reveal();
  }

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

    $this->state = $this->container->get('state');
    $this->state->set('entity_test_rev.additional_base_field_definitions', [
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
    $this->applyUpdate();

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
   * Apply update function restore.
   */
  protected function applyUpdate() {
    $complete_change_list = \Drupal::entityDefinitionUpdateManager()->getChangeList();
    if ($complete_change_list) {
      // self::getChangeList() only disables the cache and does not invalidate.
      // In case there are changes, explicitly invalidate caches.
      \Drupal::entityTypeManager()->clearCachedDefinitions();
    }
    foreach ($complete_change_list as $entity_type_id => $change_list) {
      $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);

      // Process field storage definition changes.
      if (!empty($change_list['field_storage_definitions'])) {
        $storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
        \Drupal::entityDefinitionUpdateManager()->updateFieldableEntityType($entity_type, $storage_definitions);
      }
    }
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
    $this->assertNull($repository->getActive('entity_test', $entity->id(), ['foo' => 'bar']));
  }

  /**
   * If only one revision matches a context, this revision wins.
   */
  public function testContextMatching() {
    /** @var \Drupal\revision_tree\Entity\EntityRepository $repository */
    $repository = $this->container->get('entity.repository');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->a = 'x';
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->a = 'y';
    $y->save();

    $this->mockContexts(['a' => 'x']);
    $this->assertEquals($x->getLoadedRevisionId(), $repository->getActive('entity_test_rev', $x->id())->getLoadedRevisionId());

    $this->mockContexts(['a' => 'y']);
    $this->assertEquals($y->getLoadedRevisionId(), $repository->getActive('entity_test_rev', $x->id())->getLoadedRevisionId());
  }

  /**
   * Context values can be overridden by passing them to `getActive[Multiple]`.
   */
  public function testContextOverride() {
    /** @var \Drupal\revision_tree\Entity\EntityRepository $repository */
    $repository = $this->container->get('entity.repository');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->a = 'x';
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $y = $storage->createRevision($x);
    $y->a = 'y';
    $y->save();

    $this->mockContexts(['a' => 'y']);
    $this->assertEquals($x->getLoadedRevisionId(), $repository->getActive('entity_test_rev', $x->id(), ['a' => 'x'])->getLoadedRevisionId());

    $this->mockContexts(['a' => 'x']);
    $this->assertEquals($y->getLoadedRevisionId(), $repository->getActive('entity_test_rev', $x->id(), ['a' => 'y'])->getLoadedRevisionId());
  }

  /**
   * If multiple revisions match, the higher context weight sum wins.
   */
  public function testContextOrdering() {
    /** @var \Drupal\revision_tree\Entity\EntityRepository $repository */
    $repository = $this->container->get('entity.repository');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $y = $storage->createRevision($x);
    $y->c = 'y';
    $y->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $z = $storage->createRevision($x);
    $z->a = 'x';
    $z->save();

    $this->mockContexts(['a' => 'x', 'c' => 'y']);
    $this->assertEquals($y->getLoadedRevisionId(), $repository->getActive('entity_test_rev', $x->id())->getLoadedRevisionId());
  }

  /**
   * When passing an array as the context value, this will
   */
  public function testFallbackMatching() {
    /** @var \Drupal\revision_tree\Entity\EntityRepository $repository */
    $repository = $this->container->get('entity.repository');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $x = $storage->create();
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $y = $storage->createRevision($x);
    $y->c = 'z';
    $y->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    $z = $storage->createRevision($x);
    $z->a = 'x';
    $z->save();

    $this->mockContexts(['a' => 'x', 'c' => ['y', 'z'] ]);
    $this->assertEquals($y->getLoadedRevisionId(), $repository->getActive('entity_test_rev', $x->id())->getLoadedRevisionId());
  }

  /**
   * Test the basic active-revisions query.
   */
  public function testActiveRevisionsQuery() {
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    // This has the highest matching score, but will not be in the result set
    // because it is no leaf.
    $x = $storage->create();
    $x->c = 'z';
    $x->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    // This matches the context.
    $y = $storage->createRevision($x);
    $y->a = 'x';
    $y->save();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $y */
    // This doesn't match the context.
    $z = $storage->createRevision($x);
    $z->a = 'y';
    $z->save();

    $query = $storage->getQuery();
    $query->activeRevisions(['a' => 'x', 'c' => 'z']);
    $result = $query->execute();
    $this->assertEquals([$y->getLoadedRevisionId()  => '1'], $result);
  }
}
