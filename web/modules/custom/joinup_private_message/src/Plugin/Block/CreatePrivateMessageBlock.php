<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for composing new private messages.
 *
 * This block holds links to perform actions on a private message thread.
 */
#[Block(
  id: 'joinup_private_message_block',
  admin_label: new TranslatableMarkup('Create Private Message'),
  category: new TranslatableMarkup('Private Message')
)]
class CreatePrivateMessageBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
      '#title' => $this->configFactory->get('private_message.settings')
        ->get('create_message_label'),
      '#url' => $url,
      '#attributes' => _joinup_private_message_get_ajax_modal_attributes(),
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];

    return $block;
  }

}
