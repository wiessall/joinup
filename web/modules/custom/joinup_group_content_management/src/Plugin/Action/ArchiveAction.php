<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group_content_management\Exception\ActionException;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a VBO action that archive content.
 */
#[Action(
  id: "joinup_archive",
  label: new TranslatableMarkup("Archive content"),
  type: "node",
)]
class ArchiveAction extends StateActionBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    RendererInterface $renderer,
    protected readonly TimeInterface $time,
    protected readonly AccountInterface $currentUser,
    protected readonly WorkflowHelperInterface $workflowHelper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $renderer);
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
   * @throws \Exception
   */
  public function execute(?NodeInterface $node = NULL): void {
    if (!$node instanceof EntityWorkflowStateInterface) {
      return;
    }

    $this->workflowHelper->archiveEntity(
      $node,
      $this->workflowStateChangeRevisionLogMessage($node, self::ARCHIVE_STATE, $this->t('VBO action'))
    );
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
