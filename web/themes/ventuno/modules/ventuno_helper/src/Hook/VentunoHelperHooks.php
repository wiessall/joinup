<?php

declare(strict_types=1);

namespace Drupal\ventuno_helper\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the Ventuno Helper module.
 */
class VentunoHelperHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(array $existing, string $type, string $theme, string $path): array {
    return [
      'explore_block' => [
        'variables' => [
          'data' => [],
        ],
      ],
      'iop_footer' => [
        'variables' => [
          'items' => [],
        ],
      ],
      'joinup_header_navigation' => [
        'variables' => [
          'items' => [],
          'search' => [],
        ],
      ],
      'joinup_header_get_started_cta' => [
        'variables' => [
          'url' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $attachments['#attached']['library'][] = 'ventuno_helper/destination-link';
  }

  /**
   * Implements hook_link_alter().
   */
  #[Hook('link_alter')]
  public function linkAlter(array &$variables): void {
    // @see \Drupal\joinup_distribution\Plugin\Field\FieldFormatter\TrackedHostedFileDownloadFormatter::viewElements()
    if (array_key_exists('data-joinup-tracking', $variables['options']['attributes'])) {
      $variables['options']['attributes']['class'][] = 'btn';
      $variables['options']['attributes']['class'][] = 'btn-outline-primary';
    }
    // @see collection_node_view()
    if (array_key_exists('data-drupal-group-view-mode', $variables['options']['attributes'])) {
      if ($variables['options']['attributes']['data-drupal-group-view-mode'] === 'explore_item') {
        $variables['options']['attributes']['class'][] = 'btn';
        $variables['options']['attributes']['class'][] = 'btn-secondary';
      }
    }
  }

}
