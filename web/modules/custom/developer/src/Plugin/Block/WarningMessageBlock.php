<?php

declare(strict_types=1);

namespace Drupal\developer\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
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

}
