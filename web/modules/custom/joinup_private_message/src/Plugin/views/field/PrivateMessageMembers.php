<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Plugin\views\field;

use Drupal\joinup_private_message\Service\PrivateMessageService;
use Drupal\user\UserInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'private_message_members' field.
 *
 * @ViewsField("private_message_members")
 */
class PrivateMessageMembers extends FieldPluginBase {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected PrivateMessageService $privateMessageService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('joinup_private_message.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;
    $target = $this->privateMessageService->getPrivateMessageTarget($entity);
    $ownerName = ($entity instanceof UserInterface) ? $entity->getDisplayName() : '';
    $targetName = ($target instanceof UserInterface) ? $target->getDisplayName() : '';

    if ($entity->getEntityTypeId() === 'private_message') {
      $membersMarkup = '<div>From: ' . $ownerName . '<br>To: ' . $targetName . '</div>';
      return [
        '#type' => 'markup',
        '#markup' => $membersMarkup,
      ];
    }

    return '';
  }

}
