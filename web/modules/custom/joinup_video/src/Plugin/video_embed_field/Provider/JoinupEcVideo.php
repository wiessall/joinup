<?php

declare(strict_types=1);

namespace Drupal\joinup_video\Plugin\video_embed_field\Provider;

use Drupal\Core\Url;
use Drupal\joinup_video\Plugin\video_embed_field\Provider\ValueObject\AvPortalResource;
use Drupal\video_embed_field\ProviderPluginBase;
use GuzzleHttp\TransferStats;

/**
 * An European Commission video provider plugin.
 *
 * @VideoEmbedProvider(
 *   id = "joinup_ec_video",
 *   title = @Translation("EC Audiovisual Services")
 * )
 */
class JoinupEcVideo extends ProviderPluginBase {

  /**
   * URL of the oembed endpoint.
   *
   * @see https://github.com/openeuropa/media_avportal/blob/8.x-1.x/src/AvPortalClient.php#L93
   */
  const string ENDPOINT_URL = 'https://gfdwwnbuul.execute-api.eu-west-1.amazonaws.com/avsportal/avsportal';

  /**
   * Static cache for resolved short URLs.
   *
   * @var string[]
   */
  protected static $resolvedUrls = [];

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode($width, $height, $autoplay): array {
    return [
      '#type' => 'video_embed_iframe',
      '#provider' => 'joinup_ec_video',
      '#url' => '//audiovisual.ec.europa.eu/embed/index.html',
      '#query' => [
        'ref' => $this->getVideoId(),
        'lg' => $this->getLanguagePreference(),
        'starttime' => $this->getTimeIndex() ?? NULL,
        'autoplay' => $autoplay ? 'true' : 'false',
      ],
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
  public static function getIdFromInput($input): false|string {
    // The URL may be an European Union short URL. Resolve it.
    if (preg_match('#^(?:(?:https?:)?//)?europa\.eu/\![0-9a-z]+$#i', $input)) {
      $input = static::resolveUrl($input);
    }

    $expressions = [
      // Current URLs.
      '#^(?:(?:https?:)?//)?audiovisual\.ec\.europa\.eu/(.*)/video/(?<id>I\-\d+)#i',
      // Current embed URLs.
      '#^(?:(?:https?:)?//)?audiovisual\.ec\.europa\.eu/corporateplayer/index.html(?:.*(?:video|ref)=)?(?<id>[^&\?]+)#i',
      // Old URLs.
      '#^(?:(?:https?:)?//)?ec\.europa\.eu/(?:.*ref=)?(?<id>[^&\?]+)#i',
      // Old embed URLs.
      '#^(?:(?:https?:)?//)?audiovisual\.ec\.europa\.eu/embed/index.html(?:.*ref=)?(?<id>[^&\?]+)#i',
    ];
    foreach ($expressions as $expression) {
      preg_match($expression, $input, $matches);
      if (isset($matches['id'])) {
        // Normalize the reference (I123456 > I-123456).
        return preg_replace('/^I(\d+)$/', 'I-$1', $matches['id']);
      }
    }
    return FALSE;
  }

  /**
   * Resolve AV services URL (redirects).
   *
   * @param string $url
   *   The URL to resolve.
   *
   * @return string
   *   The resolved URL.
   */
  protected static function resolveUrl(string $url): string {
    if (isset(static::$resolvedUrls[$url])) {
      return static::$resolvedUrls[$url];
    }

    $resolved_url = NULL;
    \Drupal::httpClient()->get($url, [
      'on_stats' => function (TransferStats $stats) use (&$resolved_url): void {
        $resolved_url = $stats->getEffectiveUri();
      },
    ]);
    return static::$resolvedUrls[$url] = $resolved_url ? $resolved_url->__toString() : $url;
  }

  /**
   * Gets the time index from the URL.
   *
   * @return string|false
   *   A time index parameter to pass to the frame or 0 if none is found.
   */
  protected function getTimeIndex(): string|false {
    preg_match('#[&?]starttime=(?<time_index>\d+)#i', $this->getInput(), $matches);
    return $matches['time_index'] ?? FALSE;
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
    return $this->getAvPortalResource()?->getThumbnailUrl() ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string|\Stringable {
    $langcode = mb_strtolower($this->getLanguagePreference());
    $title = $this->getAvPortalResource()?->getTitle($langcode) ?? $this->videoId;
    return $this->t('@provider | @title', [
      '@provider' => $this->getPluginDefinition()['title'],
      '@title' => $title,
    ]);
  }

  /**
   * Get the metadata from the endpoint.
   *
   * @return \Drupal\joinup_video\Plugin\video_embed_field\Provider\ValueObject\AvPortalResource|null
   *   An object of data from the endpoint or NULL if download failed.
   */
  protected function getAvPortalResource(): ?AvPortalResource {
    $url = Url::fromUri(static::ENDPOINT_URL, ['query' => ['ref' => $this->videoId]]);
    $data = $this->downloadJsonData($url->toString());
    $response = $data['response'] ?? NULL;
    if (!$response || empty($response['numFound']) || !isset($response['docs'])) {
      return NULL;
    }

    foreach ($response['docs'] as $doc) {
      if (isset($doc['ref']) && $doc['ref'] === $this->videoId) {
        return new AvPortalResource($doc);
      }
    }

    return NULL;
  }

}
