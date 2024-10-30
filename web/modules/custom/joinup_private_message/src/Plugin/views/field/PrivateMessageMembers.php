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

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected PrivateMessageService $privateMessageService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
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
  public function usesGroupBy(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {}

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array|string {
    $entity = $values->_entity;
    $target = $this->privateMessageService->getPrivateMessageTarget($entity);
    $ownerName = ($entity->getOwner() instanceof UserInterface) ? $entity->getOwner()->getDisplayName() : '';
    $targetName = ($target instanceof UserInterface) ? $target->getDisplayName() : '';

    if ($entity->getEntityTypeId() === 'private_message') {
      return [
        '#theme' => 'private_message_field_members',
        '#owner_name' => $ownerName,
        '#target_name' => $targetName,
      ];
    }

    return '';
  }

}
