<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for composing new private messages.
 *
 * This block holds links to perform actions on a private message thread.
 *
 * @Block(
 *   id = "joinup_private_message_block",
 *   admin_label = @Translation("Create Private Message"),
 *   category =  @Translation("Private Message"),
 * )
 */
class CreatePrivateMessageBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a PrivateMessageForm object.
   *
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The ID of the plugin.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccountProxyInterface $currentUser,
    protected ConfigFactory $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): CreatePrivateMessageBlock {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->hasPermission('use private messaging system'))
      ->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $url = Url::fromRoute('private_message.private_message_create');
    $block['links'][] = [
      '#type' => 'link',
      '#title' => $this->configFactory->get('private_message.settings')->get("create_message_label"),
      '#url' => $url,
    ];

    return $block;
  }

}
