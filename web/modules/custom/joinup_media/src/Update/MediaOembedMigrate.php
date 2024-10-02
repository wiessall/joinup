<?php

declare(strict_types=1);

namespace Drupal\joinup_media\Update;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\media\OEmbed\ProviderException;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\media\Plugin\media\Source\OEmbedInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrates media videos to oEmbed.
 */
final class MediaOembedMigrate implements ContainerInjectionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StorageInterface $syncStorage,
    protected UrlResolverInterface $urlResolver,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.storage.sync'),
      $container->get('media.oembed.url_resolver'),
    );
  }

  /**
   * Runs the migration.
   */
  public function run(array &$sandbox): void {
    $media_storage = $this->entityTypeManager->getStorage('media');

    if (empty($sandbox)) {
      $this->setup();

      $sandbox['mids'] = $media_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', 'video')
        ->execute();
      $sandbox['max'] = count($sandbox['mids']);
    }

    $mids = array_splice($sandbox['mids'], 0, 100);
    foreach ($media_storage->loadMultiple($mids) as $media) {
      assert($media instanceof MediaInterface);
      $url = trim($media->get('video')->getString());
      $url = $this->staticMapUrl($url);

      // Delete non-oEmbed supported media, these must be migrated manually,
      // see ISAICP-9084.
      if (!$this->isSupportedUrl($url)) {
        $media->delete();
        continue;
      }

      // Delete old media, save new one.
      $new_media = $media_storage->create([
        'bundle' => 'remote_video',
        'media_oembed_video' => $url,
      ] + $media->toArray());
      $media->delete();
      $new_media->setSyncing(TRUE)->save();
    }

    $sandbox['#finished'] = (int) empty($sandbox['mids']);
  }

  /**
   * Setup everything we need to start the migration.
   */
  protected function setup(): void {
    // Create new media type and field storages/configs from sync storage.
    $sync_storage = $this->syncStorage;
    $config_entities = [
      'media_type' => ['media.type.remote_video'],
      'field_storage_config' => ['field.storage.media.media_oembed_video'],
      'field_config' => [
        'field.field.media.remote_video.media_oembed_video',
        'field.field.media.remote_video.og_audience',
      ],
    ];

    foreach ($config_entities as $storage => $keys) {
      foreach ($keys as $key) {
        $this->entityTypeManager->getStorage($storage)
          ->create($sync_storage->read($key))
          ->save();
      }
    }
  }

  /**
   * Returns whether this is URL is supported by oEmbed & remote_video.
   */
  private function isSupportedUrl(string $url): bool {
    try {
      $provider = $this->urlResolver->getProviderByUrl($url);
      $source = $this->entityTypeManager->getStorage('media_type')->load('remote_video')->getSource();
      \assert($source instanceof OEmbedInterface);
      if (in_array($provider->getName(), $source->getProviders(), TRUE)) {
        return TRUE;
      }
    }
    catch (ResourceException | ProviderException) {
      // Ignore and return FALSE.
    }

    return FALSE;
  }

  /**
   * Static mapping for URLs.
   */
  private function staticMapUrl(string $url): string {
    return match ($url) {
      'https://www.youtube-nocookie.com/embed/SwQDVxtwmUs?autoplay=0&start=0&rel=0' => 'https://www.youtube.com/watch?v=SwQDVxtwmUs',
      default => $url,
    };
  }

}
