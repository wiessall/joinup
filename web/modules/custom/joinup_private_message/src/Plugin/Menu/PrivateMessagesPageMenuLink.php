<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Customize the link for private messages page.
 */
class PrivateMessagesPageMenuLink extends MenuLinkDefault implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    StaticMenuLinkOverridesInterface $static_override,
    protected PrivateMessageServiceInterface $privateMessageService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('private_message.service')
    );
  }

  /**
   * Add the counter of new messages to the title.
   *
   * @return string
   *   The title of the menu link with the counter.
   */
  public function getTitle(): string {
    if ($count = $this->privateMessageService->getUnreadMessageCount()) {
      return parent::getTitle() . " ($count)";
    }
    return parent::getTitle();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return 0;
  }

}
