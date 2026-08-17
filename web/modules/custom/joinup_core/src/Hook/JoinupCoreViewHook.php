<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for node entities.
 */
final class JoinupCoreViewHook {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    // Autocomplete filter for the "Groups audience" field, used only by the
    // broken links report.
    // @see \Drupal\joinup_core\Plugin\views\filter\OgAudienceAutocomplete
    $data['node__og_audience']['og_audience_target_id_autocomplete'] = [
      'group' => $this->t('Content'),
      'title' => $this->t('Groups audience (autocomplete)'),
      'help' => $this->t('The group audience, exposed with an entity autocomplete filter.'),
      'real field' => 'og_audience_target_id',
      'filter' => [
        'id' => 'og_audience_autocomplete',
      ],
    ];
  }

}
