<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\node\NodeTypeInterface;

/**
 * Helper class for joinup_i18n.
 */
class JoinupI18nHelper {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ContentTranslationManagerInterface $translationManager,
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
    // TMGMT forces the need to have the bundle set when checking if a job item
    // should be created.
    // @see \Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource::shouldCreateContinuousItem
    $bundles = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $bundles = array_filter($bundles, fn (NodeTypeInterface $bundle) => $this->translationManager->isEnabled('node', $bundle->id()));
    $settings['content']['node']['bundles'] = array_fill_keys(array_keys($bundles), 1);
    return $settings;
  }

}
