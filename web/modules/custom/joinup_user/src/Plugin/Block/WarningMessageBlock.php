<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a developers warning message block.
 */
#[Block(
  id: "developer_warning_message",
  admin_label: new TranslatableMarkup("Developers warning message"),
  category: new TranslatableMarkup("Joinup"),
)]
final class WarningMessageBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build['content'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          'Warning: You are working on production. Please, be extra cautious!',
        ],
      ],
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    // Allow access only if DEV_WARNING_BLOCK_DISABLE environment variable is
    // not set or is 0.
    $access = getenv('DEV_WARNING_BLOCK_DISABLE') ? FALSE : TRUE;
  }

}
