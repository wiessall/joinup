<?php

declare(strict_types = 1);

namespace Drupal\joinup_video\Plugin\media\Source;

use Drupal\media_avportal\Plugin\media\Source\MediaAvPortalVideoSource;

/**
 * Provides a media source plugin for European Commission video resources.
 *
 * @MediaSource(
 *   id = "joinup_ec_video",
 *   label = @Translation("Media Joinup EC Video"),
 *   description = @Translation("Media Joinup EC video plugin."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "no-thumbnail.png",
 * )
 */
class JoinupEcVideoSource extends MediaAvPortalVideoSource {

  /**
   * {@inheritdoc}
   */
  public function getSupportedUrlFormats(): array {
    $formats = parent::getSupportedUrlFormats();
    return array_merge($formats, [
      'https://europa.eu/!dV74uw',
      'https://audiovisual.ec.europa.eu/embed/index.html?sitelang=en&ref=[REF]',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedUrlPatterns(): array {
    $patterns = parent::getSupportedUrlPatterns();
    return array_merge($patterns, [
      // Backwards compatible with old style uris.
      '@ec\.europa\.eu/?(.*)ref\=(I\-\d+)@i' => 'handleVideoFullUrlPattern',
      // New style uris.
      '@audiovisual\.ec\.europa\.eu/embed/index.html?(.*)ref\=(I\-\d+)@i' => 'handleVideoFullUrlPattern',
      '@europa\.eu/\![0-9a-z]+$@i' => 'handleVideoFullUrlPattern',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function transformReferenceToUrl(string $reference): string {

    $formats = $this->getSupportedUrlFormats();
    $reference_url = reset($formats);

    if (preg_match('/I\-(\d+)/', $reference)) {
      return str_replace('[REF]', $reference, $reference_url);
    }

    return '';
  }

}
