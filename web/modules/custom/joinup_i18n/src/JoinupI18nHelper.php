<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Helper class for joinup_i18n.
 */
class JoinupI18nHelper {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * The continuous settings for TMGMT.
   */
  public const array CONTINUOUS_SETTINGS = [
    'content' => [
      'node' => ['enabled' => 1],
      'poll' => ['enabled' => 1],
      'poll_choice' => ['enabled' => 1],
    ],
  ];

  /**
   * Returns the continuous job settings with dynamic node bundles.
   *
   * @return array
   *   The continuous job settings array.
   */
  public function getContinuousSettings(): array {
    $settings = self::CONTINUOUS_SETTINGS;
    $settings['content']['node']['bundles'] = array_fill_keys(array_keys($this->entityTypeManager->getStorage('node_type')->loadMultiple()), 1);

    return $settings;
  }

}
