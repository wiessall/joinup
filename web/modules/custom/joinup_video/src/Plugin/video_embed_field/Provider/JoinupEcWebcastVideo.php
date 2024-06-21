<?php

declare(strict_types=1);

namespace Drupal\joinup_video\Plugin\video_embed_field\Provider;

use Drupal\video_embed_field\ProviderPluginBase;

/**
 * An European Commission Webcast video provider plugin.
 *
 * @VideoEmbedProvider(
 *   id = "joinup_ec_webcast_video",
 *   title = @Translation("European Commission Webcast")
 * )
 */
class JoinupEcWebcastVideo extends ProviderPluginBase {

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode($width, $height, $autoplay): array {
    $iframe = [
      '#type' => 'video_embed_iframe',
      '#provider' => 'joinup_ec_webcast_video',
      '#url' => '//webcast.ec.europa.eu/' . $this->getVideoId() . '/embed',
      '#query' => [
        'lg' => $this->getLanguagePreference(),
        'starttime' => $this->getTimeIndex(),
        'autostart' => $autoplay ? 'true' : 'false',
      ],
      '#attributes' => [
        'width' => $width,
        'height' => $height,
        'frameborder' => '0',
        'allowfullscreen' => 'true',
        'mozallowfullscreen' => 'true',
        'webkitallowfullscreen' => 'true',
      ],
    ];

    return $iframe;
  }

  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input) {
    preg_match('#^(?:(?:https?:)?//)?webcast\.ec\.europa\.eu/(.*)/embed#i', $input, $webcast);
    if (isset($webcast[1])) {
      return $webcast[1];
    }
    return FALSE;
  }

  /**
   * Gets the time index from the URL.
   *
   * @return string
   *   A time index parameter to pass to the frame or 0 if none is found.
   */
  protected function getTimeIndex(): string {
    preg_match('#[&?]starttime=(?<time_index>\d+)#i', $this->getInput(), $matches);
    return $matches['time_index'] ?? 0;
  }

  /**
   * Extracts the language preference from the URL for use in closed captioning.
   *
   * @return string
   *   The language preference if one exists or 'en' if one could not be found.
   */
  protected function getLanguagePreference(): string {
    preg_match('#[&?](?:videolang|lg)=(?<language>[a-z\-]+)#i', $this->getInput(), $matches);
    return $matches['language'] ?? 'en';
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteThumbnailUrl(): string {
    return '';
  }

}
