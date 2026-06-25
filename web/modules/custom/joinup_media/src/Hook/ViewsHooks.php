<?php

declare(strict_types=1);

namespace Drupal\joinup_media\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Views hooks of Joinup Media.
 */
class ViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   *
   * Use entity reference for group field.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    // Enable entity_reference views filter plugin, used for exposed filter in
    // media view.
    $data['media__og_audience']['og_audience_target_id']['filter']['id'] = 'entity_reference';
  }

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    return [
      'media__og_audience' => [
        'group_or_no_group' => [
          'group' => $this->t('Joinup Media'),
          'title' => $this->t('Group or no group'),
          'real field' => 'og_audience_target_id',
          'argument' => [
            'id' => 'joinup_media_group_or_null',
            'table' => 'media__og_audience',
            'field' => 'og_audience_target_id',
            'field_name' => 'og_audience',
            'entity_type' => 'media',
          ],
        ],
      ],
    ];
  }

}
