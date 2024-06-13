<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group_content_management\Exception\ActionException;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * State action base.
 */
abstract class StateActionBase extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  /**
   * Archive state.
   */
  const ARCHIVE_STATE = 'archived';

  /**
   * {@inheritdoc}
   */
  public static function finished($success, array $results, array $operations): ?RedirectResponse {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    // The access is limited at the view level.
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $objects): void {
    $messages = [];

    /** @var \Drupal\node\NodeInterface $entity */
    foreach ($objects as $entity) {
      try {
        $this->canBeExecuted($entity);
      }
      catch (ActionException $e) {
        $messages[] = $this->t('@entity can not not be processed: @error',
          [
            '@entity' => $entity->getTitle(),
            '@error' => $e->getMessage(),
          ]
        );
      }
    }

    if (!empty($messages)) {
      $list = [
        '#theme' => 'item_list',
        '#items' => $messages,
      ];
      $this->messenger()
        ->addMessage($this->renderer->render($list), MessengerInterface::TYPE_ERROR);
      return;
    }

    foreach ($objects as $entity) {
      $this->execute($entity);
    }

    $this->messenger()
      ->addMessage($this->t('Proceeded updates to @count entities', ['@count' => count($objects)]));
  }

  /**
   * Check if action can be executed on the entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity.
   *
   * @throws \Drupal\joinup_group_content_management\Exception\ActionException
   */
  abstract protected function canBeExecuted(NodeInterface $node): void;

  /**
   * Constructs the revision log message for the newly saved node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param string $newState
   *   The ID of the workflow state of the new node version.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The revision log message for the newly saved node version.
   */
  protected function getRevisionLogMessage(NodeInterface $node, string $newState): TranslatableMarkup {
    return $this->t(
      "Updated state to '@state-new' by VBO action, from revision ID '@revision-id-original' with state '@state-original'.",
      [
        '@revision-id-original' => $node->getRevisionId(),
        '@state-original' => $node instanceof EntityWorkflowStateInterface
          ? $node->getWorkflowState()
          : 'NULL',
        '@state-new' => $newState,
      ]
    );
  }

}
