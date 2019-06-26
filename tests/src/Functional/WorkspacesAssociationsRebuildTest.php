<?php

namespace Drupal\Tests\workspaces\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\workspaces\Entity\Workspace;

/**
 * Tests workspace re-indexing functionality.
 *
 * @group workspaces
 */
class WorkspacesAssociationsRebuildTest extends BrowserTestBase {

  use AssertPageCacheContextsAndTagsTrait;
  use WorkspaceTestUtilities;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'workspaces', 'node', 'revision_tree'];

  /** @var \Drupal\workspaces\WorkspaceAssociationInterface */
  protected $workspaceAssociation;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $permissions = [
      'administer workspaces',
    ];

    $mayer = $this->drupalCreateUser($permissions);
    $this->drupalLogin($mayer);

    Workspace::create([
      'id' => 'dev',
      'parent' => 'stage',
    ])->save();

    /** @var \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager */
    $workspace_manager = $this->container->get('workspaces.manager');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $nodeStorage */
    $nodeStorage = $this->container->get('entity_type.manager')->getStorage('node');

    $node = $nodeStorage->create([
      'title' => 'Test',
      'type' => 'page',
    ]);
    $node->save();

    $workspace_manager->executeInWorkspace('stage', function () use ($node, $nodeStorage) {
      $nodeStorage->createRevision($node)->save();
    });

    $workspace_manager->executeInWorkspace('dev', function () use ($node, $nodeStorage) {
      $nodeStorage->createRevision($node)->save();
    });

    $this->workspaceAssociation = $this->container->get('workspaces.association');
  }

  /**
   * Test if the rebuild action  is displayed in the workspaces list.
   */
  public function testEntityActionExists() {
    $this->drupalGet('/admin/config/workflow/workspaces');

    $this->assertSession()->statusCodeEquals(200);
    $page = $this->getSession()->getPage();
    $page
      ->find('xpath', '//td[text()="Stage"]')
      ->getParent()
      ->findLink('Rebuild associations')
      ->click();

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->addressEquals('/admin/config/workflow/workspaces/manage/stage/associations');
  }

  /**
   * Test a partial rebuild, without child workspaces.
   */
  public function testPartialRebuild() {
    $this->drupalGet('/admin/config/workflow/workspaces/manage/stage/associations');

    // Verify the initial workspace associations.
    $this->assertEquals(
      ['node' => [2 => '1']],
      $this->workspaceAssociation->getTrackedEntities('stage')
    );
    $this->assertEquals(
      ['node' => [3 => '1']],
      $this->workspaceAssociation->getTrackedEntities('dev')
    );

    \Drupal::database()->truncate('workspace_association')->execute();

    // Now all associations should be gone.
    $this->assertEmpty($this->workspaceAssociation->getTrackedEntities('stage'));
    $this->assertEmpty($this->workspaceAssociation->getTrackedEntities('dev'));


    $page = $this->getSession()->getPage();
    $page->uncheckField('include_descendants');
    $this->drupalPostForm(NULL, [], t('Rebuild Stage'));
    $this->assertText('Rebuild complete.');

    // Only the associations of the stage workspace should have been rebuilt.
    $this->assertEquals(
      ['node' => [2 => '1']],
      $this->workspaceAssociation->getTrackedEntities('stage')
    );
    $this->assertEmpty($this->workspaceAssociation->getTrackedEntities('dev'));
  }

  /**
   * Test a full rebuild, including child workspaces.
   */
  public function testFullRebuild() {
    $this->drupalGet('/admin/config/workflow/workspaces/manage/stage/associations');

    // Verify the initial workspace associations.
    $this->assertEquals(
      ['node' => [2 => '1']],
      $this->workspaceAssociation->getTrackedEntities('stage')
    );
    $this->assertEquals(
      ['node' => [3 => '1']],
      $this->workspaceAssociation->getTrackedEntities('dev')
    );

    \Drupal::database()->truncate('workspace_association')->execute();

    // Now all associations should be gone.
    $this->assertEmpty($this->workspaceAssociation->getTrackedEntities('stage'));
    $this->assertEmpty($this->workspaceAssociation->getTrackedEntities('dev'));

    $this->drupalPostForm(NULL, ['include_descendants' => 1], t('Rebuild Stage'));
    $this->assertText('Rebuild complete.');

    // Also child workspace associations should have been rebuilt in this case.
    $this->assertEquals(
      ['node' => [2 => '1']],
      $this->workspaceAssociation->getTrackedEntities('stage')
    );
    $this->assertEquals(
      ['node' => [3 => '1']],
      $this->workspaceAssociation->getTrackedEntities('dev')
    );
  }
}
