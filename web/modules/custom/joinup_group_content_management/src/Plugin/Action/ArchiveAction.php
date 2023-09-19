<?php

declare(strict_types = 1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group_content_management\Exception\ActionException;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a VBO action that archive content.
 *
 * @Action(
 *   id = "joinup_archive",
 *   label = @Translation("Archive content"),
 *   type = "node"
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
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, protected RendererInterface $renderer, protected TimeInterface $time, protected AccountInterface $currentUser) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('renderer'),
      $container->get('datetime.time'),
      $container->get('current_user')
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

    $node->setNewRevision();
    $node->isDefaultRevision(TRUE);
    $node->setRevisionLogMessage('Updated state to ' . self::ARCHIVE_STATE . ' by VBO action');
    $node->setRevisionUserId($this->currentUser->id());
    $node->setRevisionCreationTime($this->time->getRequestTime());
    $node->setWorkflowState(self::ARCHIVE_STATE);
    $node->save();
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

}
