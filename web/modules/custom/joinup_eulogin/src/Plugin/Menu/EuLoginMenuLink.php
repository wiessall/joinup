<?php

declare(strict_types=1);

namespace Drupal\joinup_eulogin\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a custom menu link that leads to the EULogin login form.
 */
class EuLoginMenuLink extends MenuLinkDefault {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    StaticMenuLinkOverridesInterface $static_override,
    protected readonly AccountInterface $currentUser,
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
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isDeletable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return $this->currentUser->isAnonymous();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // This is different on every URL, including query arguments, and is hidden
    // for authenticated users.
    return [
      'url',
      'user.roles:authenticated',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLink() {}

}
