<?php

declare(strict_types=1);

namespace Drupal\joinup_video\Plugin\video_embed_field\Provider;

use Drupal\Core\Url;
use Drupal\video_embed_field\ProviderPluginBase;

/**
 * A prezi.com presentation provider plugin.
 *
 * @VideoEmbedProvider(
 *   id = "prezi",
 *   title = @Translation("Prezi")
 * )
 */
class Prezi extends ProviderPluginBase {

  /**
   * URL of the oembed endpoint.
   *
   * @see https://github.com/iamcal/oembed/blob/master/providers/prezi.yml
   */
  const string ENDPOINT_URL = 'https://prezi.com/v/oembed';

  /**
   * Static cache for resolved short URLs.
   *
   * @var string[]
   */
  protected static $resolvedUrl = [];

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode($width, $height, $autoplay): array {
    return [
      '#type' => 'video_embed_iframe',
      '#provider' => 'prezi',
      '#url' => sprintf('https://prezi.com/embed/%s', $this->getVideoId()),
      '#attributes' => [
        'title' => $this->getName(),
        'width' => $width,
        'height' => $height,
        'frameborder' => '0',
        'allowfullscreen' => 'true',
        'mozallowfullscreen' => 'true',
        'webkitallowfullscreen' => 'true',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input): string|false {
    preg_match('#^(?:(?:https?:)?//)?(media\-)?prezi\.com/embed/(?<id>[^&\?/]+)#i', $input, $matches);
    return $matches['id'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteThumbnailUrl(): string {
    return $this->oEmbedData()['thumbnail_url'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string|\Stringable {
    return $this->t('@provider | @title', [
      '@provider' => $this->getPluginDefinition()['title'],
      '@title' => $this->oEmbedData()['title'] ?? $this->videoId,
    ]);
  }

  /**
   * Get the oEmbed data.
   *
   * @return array|null
   *   An object of data from the endpoint or NULL if download failed.
   */
  protected function oEmbedData(): ?array {
    $url = Url::fromUri(static::ENDPOINT_URL, ['query' => ['url' => "https://prezi.com/v/{$this->videoId}/"]]);
    return $this->downloadJsonData($url->toString());
  }

}
