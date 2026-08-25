<?php

declare(strict_types=1);

namespace Drupal\curated_content_listing\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for curated content listing views.
 */
final class CuratedContentListingViewHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_contextual_links_view_alter().
   */
  #[Hook('contextual_links_view_alter')]
  public function contextualLinksViewAlter(array &$element, array $items): void {
    // Change the title of the contextual links to edit entity subqueues. In our
    // business language these are called 'Curated content listings'.
    if (!empty($element['#links']['entityentity-subqueueedit-form']['title'])) {
      $element['#links']['entityentity-subqueueedit-form']['title'] = $this->t('Update curated content');
    }
  }

}
