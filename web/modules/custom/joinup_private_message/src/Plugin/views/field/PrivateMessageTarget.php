<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\joinup_private_message\Service\PrivateMessageUserService;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\user\UserInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'private_message_target' field.
 *
 * @ViewsField("private_message_target")
 */
class PrivateMessageTarget extends FieldPluginBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected PrivateMessageUserService $privateMessageService,
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
      $container->get('joinup_private_message.user_service')
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
  public function render(ResultRow $values) {
    $privateMessage = $values->_entity;
    assert($privateMessage instanceof PrivateMessageInterface);
    $target = $this->privateMessageService->getPrivateMessageTarget($privateMessage);
    assert($target instanceof UserInterface);
    $url = Url::fromRoute('entity.user.canonical', ['user' => $target->id()]);
    $link = Link::fromTextAndUrl($target->getDisplayName(), $url)
      ->toRenderable();
    return $link;
  }

}
