<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\GroupArchivableEntityInterface;
use Drupal\joinup_group_content_management\Exception\ActionException;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a VBO action that unarchive content.
 */
#[Action(
  id: "joinup_unarchive",
  label: new TranslatableMarkup("Unarchive content"),
  type: "node",
)]
class UnarchiveAction extends StateActionBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    RendererInterface $renderer,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
      $container->get('joinup_workflow.workflow_helper')
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
    if ($actual != self::ARCHIVE_STATE) {
      throw new ActionException('Entity must be ' . self::ARCHIVE_STATE);
    }

    if ($node instanceof GroupArchivableEntityInterface && $node->isParentArchived()) {
      throw new ActionException('Parent entity is archived');
    }

    $lastPublishedRevId = $this->workflowHelper->getLastPublishedRevisionId($node);
    if (!$lastPublishedRevId && !$node->isTargetWorkflowStateAllowed('published')) {
      throw new ActionException('Unarchive action is not allowed.');
    }
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

    $this->workflowHelper->unarchiveEntity(
      $node,
      $this->workflowStateChangeRevisionLogMessage($node, 'published', $this->t('VBO action'))
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = $account->hasPermission('vbo joinup unarchive action') ? AccessResult::allowed() : AccessResult::forbidden();
    return $return_as_object ? $access : $access->isAllowed();
  }

}
