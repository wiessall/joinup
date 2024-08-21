<?php

declare(strict_types=1);

namespace Drupal\joinup_video\Plugin\video_embed_field\Provider;

use Drupal\video_embed_field\ProviderPluginBase;
use GuzzleHttp\ClientInterface;

/**
 * A docs.google.com presentation plugin provider.
 *
 * @VideoEmbedProvider(
 *   id = "google_docs",
 *   title = @Translation("Google Docs")
 * )
 */
class GoogleDocs extends ProviderPluginBase {

  /**
   * The google document type.
   *
   * @var string
   */
  protected $docType;

  /**
   * Create a plugin with the given input.
   *
   * @param array $configuration
   *   The configuration of the plugin.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client.
   */
  public function __construct($configuration, $plugin_id, array $plugin_definition, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $http_client);
    $this->docType = $this->getTypeFromInput($configuration['input']);
  }

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode($width, $height, $autoplay): array {
    return [
      '#type' => 'video_embed_iframe',
      '#provider' => 'google_docs',
      '#url' => sprintf('https://docs.google.com/%s/d/%s/viewform?embedded=true', $this->getDocType(), $this->getVideoId()),
      '#attributes' => [
        'title' => $this->getName(),
        'width' => $width,
        'height' => $height,
        'frameborder' => '0',
        'allowfullscreen' => 'allowfullscreen',
      ],
    ];
  }

  /**
   * Parses the input and returns a list of matches.
   *
   * @param string $input
   *   The input url.
   *
   * @return array
   *   An array of matches related to the url. The two specific values returned
   *   are id and type.
   */
  public static function getDataFromInput($input): array {
    preg_match('#^(?:(?:https?:)?//)?docs\.google\.com/(?<type>[^/]+)/d/(e/)?(?<id>[^/]+).*?#i', $input, $matches);
    return $matches;
  }

  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input): string|false {
    $matches = static::getDataFromInput($input);
    return $matches['id'] ?? FALSE;
  }

  /**
   * Return the google document type.
   *
   * @param string $input
   *   The input url.
   *
   * @return string|false
   *   The type of the document, e.g. forms or presentation. False if there is
   *   no match found.
   */
  public static function getTypeFromInput($input): string|false {
    $matches = static::getDataFromInput($input);
    return $matches['type'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteThumbnailUrl(): string {
    return '';
  }

  /**
   * Returns the document type.
   *
   * @return string
   *   The document type.
   */
  public function getDocType(): string {
    return $this->docType;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string|\Stringable {
    return $this->t('@provider | @id', [
      '@provider' => $this->getPluginDefinition()['title'],
      '@id' => $this->videoId,
    ]);
  }

}
