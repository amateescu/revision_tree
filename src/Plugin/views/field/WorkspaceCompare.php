<?php

namespace Drupal\revision_tree\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\ViewsHandlerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to show a revisions comparison.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("workspace_compare")
 */
class WorkspaceCompare extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Views Handler Plugin Manager.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected $joinHandler;

  /**
   * Constructs a new WorkspaceCompare field.
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

    $this->source_field = $this->query->addField($join_table_alias, 'vid', 'num_source', ['count' => TRUE]);

    $definition['extra'][0]['value'] = $target;

    $join = $this->joinHandler->createInstance('standard', $definition);
    $join_table_alias = $query->addTable($query_base_table, $this->relationship, $join);

    $this->target_field = $this->query->addField($join_table_alias, 'vid', 'num_target', ['count' => TRUE, 'distinct' => false]);

    $this->name_alias = $this->view->storage->get('base_table') . '.nid';
    $this->query->addGroupBy($this->name_alias);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    if (empty($values->{$this->target_field})) {
      return $this->t('Identical');
    } elseif ($values->{$this->source_field} > $values->{$this->target_field}) {
      return $this->t('Updated');
    } elseif ($values->{$this->target_field} == $values->{$this->source_field}) {
      return $this->t('Conflict');
    } else {
      return $this->t('Outdated');
    }
  }

}
