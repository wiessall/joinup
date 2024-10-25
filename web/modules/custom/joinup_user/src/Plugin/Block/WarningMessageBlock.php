<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
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
  category: new TranslatableMarkup("Portal"),
)]
final class WarningMessageBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $warnings = [
      $this->t('You are working on production. Please, be extra cautious!'),
    ];

    $build['content'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => $warnings,
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    // Allow the environment variable to not show this block on development
    // environment so that devs do not get used to the warning.
    $access = !getenv('IS_DDEV_PROJECT');
    return AccessResult::allowedIf($access);
  }

}
