<?php

declare(strict_types = 1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\joinup_group_content_management\Exception\ActionException;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;

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
