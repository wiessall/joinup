<?php

declare(strict_types=1);

namespace Drupal\joinup_tmgmt\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\joinup_tmgmt\Tmgmt\BlockFieldProcessor;
use Drupal\joinup_tmgmt\Tmgmt\IframeFieldProcessor;
use Drupal\joinup_tmgmt\Tmgmt\SocialMediaFieldProcessor;

/**
 * Hook implementations for joinup_tmgmt.
 */
final class JoinupTmgmtHooks {

  /**
   * Implements hook_field_info_alter().
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(array &$info): void {
    if (isset($info['block_field'])) {
      $info['block_field']['tmgmt_field_processor'] = BlockFieldProcessor::class;
    }

    if (isset($info['iframe'])) {
      $info['iframe']['tmgmt_field_processor'] = IframeFieldProcessor::class;
    }

    if (isset($info['social_media_links_field'])) {
      $info['social_media_links_field']['tmgmt_field_processor'] = SocialMediaFieldProcessor::class;
    }
  }

}
