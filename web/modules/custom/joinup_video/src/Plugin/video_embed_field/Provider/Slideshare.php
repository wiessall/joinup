<?php

declare(strict_types=1);

namespace Drupal\joinup_video\Plugin\video_embed_field\Provider;

use Drupal\Core\Url;
use Drupal\video_embed_field\ProviderPluginBase;

/**
 * A slideshare.net presentation plugin provider.
 *
 * @VideoEmbedProvider(
 *   id = "slideshare",
 *   title = @Translation("Slideshare")
 * )
 */
class Slideshare extends ProviderPluginBase {

  /**
   * URL of the oembed endpoint.
   *
   * @see https://github.com/iamcal/oembed/blob/master/providers/slideshare.yml
   */
  const string ENDPOINT_URL = 'https://www.slideshare.net/api/oembed/2';

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
      '#provider' => 'slideshare',
      '#url' => sprintf('https://www.slideshare.net/slideshow/embed_code/key/%s', $this->getVideoId()),
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
    preg_match('#^(?:(?:https?:)?//)?(www\.)slideshare\.net/slideshow/embed_code/key/(?<id>[^&\?/]+)#i', $input, $matches);
    return $matches['id'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteThumbnailUrl(): string {
    return '';
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
   *   An object of data from the oembed endpoint or NULL if download failed.
   */
  protected function oEmbedData(): ?array {
    $url = Url::fromUri(static::ENDPOINT_URL, ['query' => ['url' => $this->getInput(), 'format' => 'json']]);
    return $this->downloadJsonData($url->toString());
  }

}
