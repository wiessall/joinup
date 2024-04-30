<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group_content_management\Exception\ActionException;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a VBO action that archive content.
 *
 * @Action(
 *   id = "joinup_archive",
 *   label = @Translation("Archive content"),
 *   type = "node",
 *   requirements = {
 *      "_custom_access" = TRUE,
 *   },
 * )
 */
class ArchiveAction extends StateActionBase {

  /**
   * Constructs object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\joinup_workflow\WorkflowHelperInterface $workflowHelper
   *   The workflow helper service.
   */
  public function __construct(
    array $configuration,
    string $pluginId,
    $pluginDefinition,
    protected RendererInterface $renderer,
    protected TimeInterface $time,
    protected AccountInterface $currentUser,
    protected WorkflowHelperInterface $workflowHelper,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('joinup_workflow.workflow_helper')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function execute(?NodeInterface $node = NULL): void {
    if (!$node instanceof EntityWorkflowStateInterface) {
      return;
    }

    $this->workflowHelper->archiveEntity($node, 'Updated state to ' . self::ARCHIVE_STATE . ' by VBO action');
  }

  /**
   * {@inheritdoc}
   */
  protected function canBeExecuted(NodeInterface $node): void {
    if (!$node instanceof EntityWorkflowStateInterface) {
      throw new ActionException('This content does not support workflow.');
    }

    $actual = $node->getWorkflowState();
    if ($actual == self::ARCHIVE_STATE) {
      throw new ActionException('It is already ' . self::ARCHIVE_STATE);
    }

    if (!$node->isTargetWorkflowStateAllowed(self::ARCHIVE_STATE)) {
      throw new ActionException('It is not allowed to move ' . $actual . ' to ' . self::ARCHIVE_STATE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function customAccess(AccountInterface $account, ViewExecutable $view): bool {
    /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
    $group = \Drupal::service('og.context')->getGroup();

    /** @var \Drupal\joinup_group\JoinupGroupManagerInterface $groupManager */
    $groupManager = \Drupal::service('joinup_group.group_manager');
    if (!$group) {
      return FALSE;
    }

    $permission = 'vbo joinup archive action';
    return $groupManager->access($group, $permission, $permission, $account)
      ->isAllowed();
  }

}
