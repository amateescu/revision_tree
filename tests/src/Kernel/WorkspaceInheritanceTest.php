<?php

namespace Drupal\Tests\revision_tree\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\workspaces\Entity\Workspace;

/**
 * Detail test inheritance indexing and rebuilding in different scenarios.
 *
 * @group #slow
 * @group revision_tree
 */
class WorkspaceInheritanceTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use EntityReferenceTestTrait;
  use NodeCreationTrait;
  use UserCreationTrait;
  use ViewResultAssertionTrait;
  use WorkspaceTestTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The workspace association service.
   *
   * @var \Drupal\revision_tree\RevisionTreeWorkspaceAssociationInterface
   */
  protected $workspaceAssociation;

  /**
   * Creation timestamp that should be incremented for each new entity.
   *
   * @var int
   */
  protected $createdTimestamp;

  /**
   * An array of nodes created before installing the Workspaces module.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'filter',
    'node',
    'text',
    'user',
    'system',
    'views',
    'workspaces',
    'revision_tree',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeManager = \Drupal::entityTypeManager();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    $this->installConfig(['filter', 'node', 'system']);

    $this->installSchema('system', ['key_value_expire', 'sequences']);
    $this->installSchema('node', ['node_access']);

    $this->createContentType(['type' => 'page']);

    $this->setCurrentUser($this->createUser(['administer nodes']));

    $this->initializeWorkspacesModule();

    // Create the following workspace hierarchy:
    // live
    // - stage
    //   - dev
    //     - local_1
    //     - local_2
    // - qa
    $this->workspaces['dev'] = Workspace::create(
      ['id' => 'dev', 'parent' => 'stage']
    );
    $this->workspaces['dev']->save();
    $this->workspaces['local_1'] = Workspace::create(
      ['id' => 'local_1', 'parent' => 'dev']
    );
    $this->workspaces['local_1']->save();
    $this->workspaces['local_2'] = Workspace::create(
      ['id' => 'local_2', 'parent' => 'dev']
    );
    $this->workspaces['local_2']->save();
    $this->workspaces['qa'] = Workspace::create(
      ['id' => 'qa', 'parent' => 'live']
    );
    $this->workspaces['qa']->save();

    $this->nodes['a'] = $this->createNode(['title' => 'A'])->id();
    $this->nodes['b'] = $this->createNode(['title' => 'A'])->id();
    $this->workspaceAssociation = \Drupal::service('workspaces.association');

  }

  protected function executeEntityOperations($operations) {
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $nodeStorage */
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    foreach ($operations as $index => $operation) {
      list($workspace, $label, $merge_parent) = $operation;
      $this->workspaceManager->executeInWorkspace($workspace, function () use ($label, $index, $merge_parent, $workspace, $nodeStorage) {
        $node = $nodeStorage->createRevision($nodeStorage->load($this->nodes[$label]));
        $node->setTitle(strtoupper($label) . ' - ' . $index . ' ' . $workspace);

        if ($merge_parent) {
          $node->revision_merge_parent = $merge_parent;
        }
        $node->save();
      });
    }
  }

  /**
   * Test hierarchy different inheritance scenarios.
   *
   * @dataProvider hierarchyOperations
   */
  public function testHierarchy($operations, $expected_associations) {
    $this->executeEntityOperations($operations);
    $this->assertWorkspaceAssociations($expected_associations, 'node');
  }

  /**
   * Test index rebuilds for different inheritance scenarios.
   *
   * @dataProvider hierarchyOperations
   */
  public function testIndexRebuild($operations, $expected_associations) {
    $this->executeEntityOperations($operations);
    \Drupal::database()->truncate('workspace_association');
    foreach (array_keys($this->workspaces) as $workspace) {
      $this->workspaceAssociation->rebuildAssociations('node', $workspace);
    }
    $this->assertWorkspaceAssociations($expected_associations, 'node');
  }

  public function hierarchyOperations() {
    return [
      'no workspace revisions' => [
        // As long as there are no operations in workspaces, there are no associations.
        [

        ],
        [
          'stage' => [],
          'qa' => [],
          'dev' => [],
          'local_1' => [],
          'local_2' => [],
        ],
      ],
      'single revision' => [
        // Creating a new revision in QA only creates an association there.
        [
          ['qa', 'a', NULL],            // Create revision 3 in workspace QA.
        ],
        [
          'stage' => [],                // There are no revisions in Stage.
          'qa' => [3],                  // Revision 3 is associated to workspace QA.
        ],
      ],
      'single nested revision' => [
        // Creating a new revision in a nested workspace only creates an association there.
        [
          ['local_1', 'a', NULL],       // Create revision 3 in Local 1.
        ],
        [
          'stage' => [],                // There are no revisions in Stage.
          'local_1' => [3],             // Revision 3 is in Local 1.
        ],
      ],
      'simple inheritance' => [
        // Creating a new revision in stage will inherit to dev and local_1/2.
        [
          ['stage', 'a', NULL],         // Create revision 3 in Stage
        ],
        [
          'stage' => [3],               // Revision 3 is in stage
          'dev' => [3],                 // Revision 3 is inherited to dev
          'local_1' => [3],             // Revision 3 is inherited to Local 1
          'local_2' => [3],             // Revision 3 is inherited to Local 2
        ],
      ],
      'partial inheritance' => [
        // A revision in dev should override the stage revision for this branch.
        [
          ['stage', 'a', NULL],         // Create revision 3 in Stage
          ['dev', 'a', NULL],           // Create revision 4 in dev, with 3 as parent
        ],
        [
          'stage' => [3],               // Revision 3 is in Stage
          'dev' => [4],                 // Dev uses revision 4
          'local_1' => [4],             // Local 1 inherits revision 4
          'local_2' => [4],             // Local 2 inherits revision 4
        ],
      ],
      'no leaf inheritance' => [
        // A revision in a leaf workspace should not affect any others.
        [
          ['stage', 'a', NULL],         // Create revision 3 in Stage
          ['local_1', 'a', NULL],       // Create revision 4 in Local 1
        ],
        [
          'stage' => [3],               // Stage uses revision 3
          'dev' => [3],                 // Dev inherits revision 3 from Stage
          'local_1' => [4],             // Local 1 uses the custom revision 4
          'local_2' => [3],             // Local 2 inherits revision 3 from Dev and Stage
        ],
      ],
      'multiple nodes' => [
        // Saving and re-indexing multiple nodes works as expected.
        [
          ['stage', 'a', NULL],         // Create revision 3 of entity a in Stage
          ['stage', 'b', NULL],         // Create revision 4 of entity b in Stage
        ],
        [
          'stage' => [3, 4],            // Stage is related to the latest revisions of both entities.
          'dev' => [3, 4],              // Dev inherits both revisions from Stage
          'local_1' => [3, 4],          // Local 1 inherits both revisions from Dev and Stage
          'local_2' => [3, 4],          // Local 2 inherits both revisions from Dev and Stage
        ],
      ],
      // Leaf revisions have always higher precedence, no matter when they
      // have been created
      'parent before child' => [
        [
          ['dev', 'a', NULL],           // Create revision 3 in Dev
          ['local_1', 'a', NULL],       // Create revision 4 in Local 1
        ],
        [
          'dev' => [3],                 // Dev uses revision 3
          'local_1' => [4],             // Local 1 uses its custom revision 4
          'local_2' => [3],             // Local 2 inherits revision 3 from Dev
        ],
      ],
      'child before parent' => [
        [
          ['local_1', 'a', NULL],       // Create revision 3 in Local 1
          ['dev', 'a', NULL],           // Create revision 4 in Dev
        ],
        [
          'dev' => [4],                 // Dev uses revision 4
          'local_1' => [3],             // Local 1 uses its custom revision 3
          'local_2' => [4],             // Local 2 inherits revision 4 from Dev
        ],
      ],
      // Merge a child into a parent.
      'merge child into parent' => [
        [
          ['dev', 'a', NULL],           // Create revision 3 in Dev
          ['local_1', 'a', NULL],       // Create revision 4 in Local 1
          ['dev', 'a', 4],              // Create merge revision 5 with merge parent 4 in dev
        ],
        [
          'dev' => [5],                 // Dev uses the merge revision 5
          'local_1' => [5],             // Local 1 inherits the merge revision 5
        ],
      ],
      // Merge parent into child.
      'merge parent into child' => [
        [
          ['dev', 'a', NULL],           // Create revision 3 in Dev
          ['local_1', 'a', NULL],       // Create revision 4 in Local 1
          ['local_1', 'a', 3],          // Create merge revision 5 with merge parent 3 in Local 1
        ],
        [
          'dev' => [3],                 // Dev still uses revision 3
          'local_1' => [5],             // Local 1 uses the merged revision 5
        ],
      ],
      // Merge child into grandparent
      'merge child into grandparent while inheriting' => [
        [
          ['stage', 'a', NULL],         // Create revision 3 in Stage
          ['local_1', 'a', NULL],       // Create revision 4 in Local 1
          ['stage', 'a', 4]             // Create merge revision 5 with merge parent 4 in Stage
        ],
        [
          'stage' => [5],               // Stage uses the new merge revision 5
          'dev' => [5],                 // Dev inherits the merged revision from Stage
          'local_1' => [4],             // Local 1 still uses its custom revision, since it has not been merged into its parent
        ]
      ],
      'merge child into grandparent while not inheriting' => [
        [
          ['stage', 'a', NULL],         // Create revision 3 in Stage
          ['dev', 'a', NULL],           // Create revision 4 in Dev
          ['local_1', 'a', NULL],       // Create revision 5 in Local 1
          ['stage', 'a', 5],            // Create merge revision 6 by merging Local 1 into Stage
        ],
        [
          'stage' => [6],               // Stage uses the new merge revision 6
          'dev' => [4],                 // Dev still uses the custom revision 4
          'local_1' => [5],             // Local 1 still uses its custom revision 5 since it has not been merged into its parent
        ]
      ],
      // Merge unrelated.
      'merge unrelated nodes' => [
        [
          ['local_1', 'a', NULL],       // Create revision 3 in Local 1
          ['local_2', 'a', NULL],       // Create revision 4 in Local 2
          ['local_1', 'a', 4],          // Create revision 5 by merging Local 2 into Local 1
        ],
        [
          'local_1' => [5],             // Local 1 uses the merge revision 5
          'local_2' => [4],             // Local 2 still  uses revision 4
        ],
      ],
      // A combined use case
      'combined' => [
        [
          ['local_1', 'a', NULL],       // Create revision 3 in Local 1
          ['local_1', 'a', NULL],       // Create revision 4 in Local 1
          ['dev', 'b', NULL],           // Create revision 5 in Dev
          ['dev', 'a', NULL],           // Create revision 6 of entity B in Dev
          ['stage', 'a', NULL],         // Create revision 7 in Stage
          ['qa', 'b', NULL],            // Create revision 8 of entity B in QA
          ['qa', 'b', NULL],            // Create revision 9 of entity B in QA
        ],
        [
          'local_1' => [4, 5],          // Local 1 uses local revision 4 and inherits revision 5 from dev
          'local_2' => [5, 6],          // Local 2 inherits revisions 5 and 6 from dev.
          'dev' => [5, 6],              // Dev uses its local revisions 5 and 6
          'stage' => [7],               // Stage only has one local revision 7
          'qa' => [9],                  // QA uses its second revision 9
        ]
      ]
    ];
  }

  /**
   * Checks the workspace_association entries for a test scenario.
   *
   * @param array $expected
   *   An array of expected values, as defined in ::testWorkspaces().
   * @param string $entity_type_id
   *   The ID of the entity type that is being tested.
   */
  protected function assertWorkspaceAssociations(
    array $expected,
    $entity_type_id
  ) {
    foreach ($expected as $workspace_id => $expected_tracked_revision_ids) {
      $tracked_entities = $this->workspaceAssociation->getTrackedEntities(
        $workspace_id,
        $entity_type_id
      );
      $tracked_revision_ids = isset($tracked_entities[$entity_type_id]) ? $tracked_entities[$entity_type_id] : [];
      $this->assertEquals(
        $expected_tracked_revision_ids,
        array_keys($tracked_revision_ids),
        sprintf(
          'Expected (%s) in workspace %s but got (%s) instead.',
          implode(', ', $expected_tracked_revision_ids),
          $workspace_id,
          implode(
            ', ',
            array_keys($tracked_revision_ids)
          )
        )
      );
    }
  }
}
