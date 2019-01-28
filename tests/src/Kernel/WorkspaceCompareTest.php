<?php

namespace Drupal\Tests\revision_tree\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\user\Entity\User;
use ReflectionClass;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\entity_test\Entity\EntityTestRev;
use Symfony\Component\DependencyInjection\Definition;
use Drupal\workspaces\Entity\Workspace;
use Drupal\views\Views;
use Drupal\views\Tests\ViewTestData;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\NodeCreationTrait;

class WorkspaceCompareTest extends ViewsKernelTestBase {
  use UserCreationTrait;

  public static $testViews = ['content_status'];

  public static $modules = [
    'entity_test',
    'revision_tree',
    'user',
    'node',
    'system',
    'workspaces',
    'views',
    'revision_tree_views_config',
    'field',
  ];

  /**
   * Enables the Workspaces module and creates two workspaces.
   */
  protected function initializeWorkspacesModule() {
    // Enable the Workspaces module here instead of the static::$modules array
    // so we can test it with default content.
    $this->enableModules(['workspaces']);
    $this->container = \Drupal::getContainer();
    $this->entityTypeManager = \Drupal::entityTypeManager();

    $this->installEntitySchema('workspace');
    $this->installEntitySchema('workspace_association');

    // Create two workspaces by default, 'live' and 'stage'.
    $this->workspaces = [];
    $this->workspaces['live'] = Workspace::create(['id' => 'live']);
    $this->workspaces['live']->save();
    $this->workspaces['stage'] = Workspace::create(['id' => 'stage']);
    $this->workspaces['stage']->save();

    $permissions = [
      'create workspace',
      'edit any workspace',
      'view any workspace',
    ];
    $this->setCurrentUser($this->createUser($permissions));
  }

  /**
   * Sets a given workspace as active.
   *
   * @param string $workspace_id
   *   The ID of the workspace to switch to.
   */
  protected function switchToWorkspace($workspace_id) {
    // Switch the test runner's context to the specified workspace.
    $workspace = $this->entityTypeManager->getStorage('workspace')->load($workspace_id);
    \Drupal::service('workspaces.manager')->setActiveWorkspace($workspace);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp(false);

    ViewTestData::createTestViews(get_class($this), ['revision_tree_views_config']);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('entity_test_rev');

    $this->initializeWorkspacesModule();

    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_rev');

    $type = NodeType::create(['type' => 'testtype']);
    $status = $type->save();

    $this->switchToWorkspace('live');
    // Create revisions.
    $revision_1 = Node::create(['type' => 'testtype', 'title' => 'test node']);
    $revision_1->save();

    $revision_2 = $storage->createRevision($revision_1, TRUE);
    $revision_2->save();

    $revision_3 = $storage->createRevision($revision_2, FALSE);
    $revision_3->save();
    // Switch workspace and add more revisions.
    $this->switchToWorkspace('stage');
    $revision_4 = $storage->createRevision($revision_2, FALSE);

    $revision_5 = $storage->createRevision($revision_2, FALSE);
    $revision_5->revision_parent->target_id = $revision_1->getRevisionId();

    $revision_6 = $storage->createRevision($revision_2, FALSE);
    $revision_6->revision_parent->target_id = $revision_2->getRevisionId();
    $revision_6->revision_parent->merge_target_id = $revision_3->getRevisionId();
  }

  /**
   * Test workspace compare filter.
   */
  public function testWorkspaceCompareFilter() {
    $view = Views::getView('content_status');
    $view->setDisplay();
    $args = ['live', 'stage'];
    $view->preExecute($args);
    $view->execute();

    $this->assertEqual(1, count($view->result));
    foreach ($view->result as $key => $value)  {
      $this->assertEqual(2, $value->num_source);
      $this->assertEqual(0, $value->num_target);
    }
    $rendered_view = $view->preview();
    $output = $this->container->get('renderer')->renderRoot($rendered_view);
    $this->assertContains('Identical', (string) $output);
  }

}
