<?php

declare(strict_types=1);

namespace Drupal\developer\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a developers warning message block.
 *
 * @Block(
 *   id = "developer_warning_message",
 *   admin_label = @Translation("Developers warning message"),
 *   category = @Translation("Joinup"),
 * )
 */
final class WarningMessageBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build['content'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          'Warning: You are working on production. Configuration is unlocked. Please, be extra cautious!',
        ],
      ],
    ];
    return $build;
  }

}
