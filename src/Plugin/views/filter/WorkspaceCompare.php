<?php

namespace Drupal\revision_tree\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * Provides filtering by comparison status.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("workspace_compare")
 */
class WorkspaceCompare extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new WorkspaceCompare.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager Service.
   * @param \Drupal\views\Plugin\ViewsHandlerManager $join_handler
   *   Views Handler Plugin Manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ViewsHandlerManager $join_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->joinHandler = $join_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.views.join')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->valueOptions = [
      'identical' => $this->t('Identical'),
      'updated' => $this->t('Updated'),
      'outdated' => $this->t('Outdated'),
      'conflict' => $this->t('Conflict')
    ];

    $this->valueFormType = 'select';
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $source = $this->view->args[0];
    $target = $this->view->args[1];

    $query = $this->query;
    $query_base_table = $this->relationship ?: $this->view->storage->get('base_table');

    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());

    $keys = $entity_type->getKeys();
    $definition = [
      'table' => 'node_revision',
      'type' => 'LEFT',
      'field' => $keys['revision'],
      'left_table' => $query_base_table,
      'left_field' => $keys['revision'],
      'extra' => [
        ['field' => 'workspace', 'value' => $source],
      ],
    ];

    $join = $this->joinHandler->createInstance('standard', $definition);
    $join_table_alias = $query->addTable($query_base_table, $this->relationship, $join);

    $this->name_alias = $this->view->storage->get('base_table') . '.nid';
    $num_source_alias = $this->query->addField($join_table_alias, 'vid', 'num_source', ['count' => TRUE, 'distinct' => TRUE]);

    $definition['extra'][0]['value'] = $target;

    $join = $this->joinHandler->createInstance('standard', $definition);
    $join_table_alias = $query->addTable($query_base_table, $this->relationship, $join);

    $this->name_alias = $this->view->storage->get('base_table') . '.nid';
    $num_target_alias = $this->query->addField($join_table_alias, 'vid', 'num_target', ['count' => TRUE, 'distinct' => TRUE]);

    $this->query->addGroupBy($this->name_alias);

    switch ($this->value[0]) {
      case 'identical':
        $this->query->addHavingExpression(0, $num_target_alias . ' = 0', []);
        break;
      case 'updated':
        $this->query->addHavingExpression(0, $num_source_alias . ' > ' . $num_target_alias, []);
        break;
      case 'outdated':
        $this->query->addHavingExpression(0, $num_source_alias . ' < ' . $num_target_alias, []);
        break;
      case 'conflict':
        $this->query->addHavingExpression(0, $num_source_alias . ' = ' . $num_target_alias . ' AND ' . $num_source_alias . ' > 0', []);
        break;
      default:
        break;
    }
  }
}
