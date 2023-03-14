<?php

declare(strict_types = 1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\joinup_group_content_management\Exception\ActionException;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a VBO action that unarchive content.
 *
 * @Action(
 *   id = "joinup_unarchive",
 *   label = @Translation("Unarchive content"),
 *   type = "node"
 * )
 */
class UnarchiveAction extends StateActionBase {

  /**
   * Get previous revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity.
   *
   * @return int|null
   *   Previous revision.
   */
  protected function getPreviousRevisionId(NodeInterface $node): ?int {
    $entity_type = $node->getEntityTypeId();

    if (!$storage = $this->entityTypeManager->getStorage($entity_type)) {
      return NULL;
    }

    $revision_ids = $storage->getQuery()
      ->allRevisions()
      ->condition($this->entityTypeManager->getDefinition($entity_type)
        ->getKey('id'), $node->id())
      ->sort($this->entityTypeManager->getDefinition($entity_type)
        ->getKey('revision'), 'DESC')
      ->range(1, 1)
      ->execute();
    if ($revision_ids) {
      return array_keys($revision_ids)[0];
    }

    return NULL;
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

    $previous = $this->getPreviousRevision($node);
    $previous->setNewRevision();
    $previous->isDefaultRevision(TRUE);
    $previous->setRevisionLogMessage('Revert archive state by VBO action ');
    $previous->setRevisionUserId($this->currentUser->id());
    $previous->setRevisionCreationTime($this->time->getRequestTime());
    $previous->save();
  }

  /**
   * Gets previous revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface|null
   *   Revisionable entity.
   */
  protected function getPreviousRevision(NodeInterface $node): ?RevisionableInterface {
    $previousRevisionId = $this->getPreviousRevisionId($node);
    if (!$previousRevisionId) {
      return NULL;
    }

    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($node->getEntityTypeId());

    return $storage->loadRevision($previousRevisionId);
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


    $previous = $this->getPreviousRevision($node);
    if (!$previous) {
      throw new ActionException('Previous revision has not been found');
    }
  }

}
