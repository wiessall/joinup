<?php

declare(strict_types = 1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group_content_management\Exception\ActionException;
use Drupal\node\NodeInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * Constructs object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected RendererInterface $renderer, protected TimeInterface $time, protected AccountInterface $currentUser, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('entity_type.manager')
    );
  }

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

}
