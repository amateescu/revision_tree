<?php

namespace Drupal\Tests\revision_tree\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\ContextProvider\CurrentLanguageContext;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\revision_tree\Plugin\Context\RevisionNegotiationContextInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\Definition;

class RevisionNegotiationTest extends EntityKernelTestBase {

  public static $modules = [
    'entity_test',
    'revision_tree',
    'user',
    'system',
  ];

  /**
   * Construct a fake context provider to test context collection.
   */
  public static function contextProviderFactory() {
    // Create 4 different revision negotiation contexts relating to 4
    // entity fields.
    $weights = [
      'a' => 1,
      'b' => 1,
      'c' => 2,
      'd' => -10,
    ];

    $contexts = ['foo', new Context(new ContextDefinition())];

    foreach ($weights as $context => $weight) {
      $contexts[$context] = new class (new ContextDefinition(), null, $context, $weight) extends Context implements RevisionNegotiationContextInterface {

        protected $field;
        protected $weight;

        public function __construct(ContextDefinitionInterface $context_definition, ?mixed $context_value = NULL, $field = '', $weight = 0) {
          parent::__construct($context_definition, $context_value);
          $this->field = $field;
          $this->weight = $weight;
        }

        public function applies(EntityTypeInterface $entityType) {
          return $entityType->id() === 'entity_test_rev';
        }

        public function getContextField() {
          return $this->field;
        }

        public function getWeight() {
          return $this->weight;
        }
      };
    }

    $provider = new class($contexts) implements ContextProviderInterface {
      protected $contexts;

      public function __construct($contexts) {
        $this->contexts = $contexts;
      }

      public function getRuntimeContexts(array $unqualified_context_ids) {
        return $this->contexts;
      }
      public function getAvailableContexts() {
        return $this->contexts;
      }
    };
    return $provider;
  }

  /**
   * Inject a fake context provider.
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    // Inject a context provider.
    // The definition has to be "any" context provider class, or the compiler
    // pass will fail.
    $contextProviderDefinition = new Definition(CurrentLanguageContext::class);
    $contextProviderDefinition->setFactory('Drupal\Tests\revision_tree\Kernel\RevisionNegotiationTest::contextProviderFactory');
    $contextProviderDefinition->addTag('context_provider');
    $this->container->setDefinition('test_context_provider', $contextProviderDefinition);
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

  public function testRevisionNegotiationDiscovery() {

    $discovery =$this->container->get('revision_tree.revision_negotiation_discovery');

    $simple = $this->entityManager->getDefinition('entity_test');
    $this->assertEquals([], $discovery->getEntityContextDefinitions($simple));

    $rev = $this->entityManager->getDefinition('entity_test_rev');
    $this->assertEquals([
      'a' => [
        'field' => 'a',
        'weight' => 1,
      ],
      'b' => [
        'field' => 'b',
        'weight' => 1,
      ],
      'c' => [
        'field' => 'c',
        'weight' => 2,
      ],
      'd' => [
        'field' => 'd',
        'weight' => -10,
      ],
    ], $discovery->getEntityContextDefinitions($rev));
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
}
