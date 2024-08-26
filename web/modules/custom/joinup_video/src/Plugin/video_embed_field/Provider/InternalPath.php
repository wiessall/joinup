<?php

declare(strict_types=1);

namespace Drupal\joinup_video\Plugin\video_embed_field\Provider;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\video_embed_field\ProviderPluginBase;

/**
 * An iframe pointing to site itself.
 *
 * @VideoEmbedProvider(
 *   id = "internal_path",
 *   title = @Translation("Internal path")
 * )
 */
class InternalPath extends ProviderPluginBase {

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode($width, $height, $autoplay): array {
    $path = $this->getVideoId();
    if (UrlHelper::isExternal($path)) {
      return [];
    }

    return [
      '#type' => 'video_embed_iframe',
      '#provider' => 'internal_path',
      '#url' => Url::fromUri("internal:/$path")
        ->setOption('base_url', $GLOBALS['base_url'])
        ->setAbsolute()
        ->toString(),
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
   * {@inheritdoc}
   */
  public static function isApplicable($input): bool {
    return !UrlHelper::isExternal($input) || UrlHelper::externalIsLocal($input, $GLOBALS['base_url']);
  }

  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input): string {
    if (str_starts_with($input, $GLOBALS['base_url'])) {
      $input = substr($input, strlen($GLOBALS['base_url']));
    }
    elseif (str_starts_with($input, base_path())) {
      $input = substr($input, strlen(base_path()) - 1);
    }
    return ltrim($input, '/');
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
    return $this->t('@provider | @id', [
      '@provider' => $this->getPluginDefinition()['title'],
      '@id' => $this->videoId,
    ]);
  }

}
