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
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrates media videos to oEmbed.
 */
final class MediaOembedMigrate implements ContainerInjectionInterface {

  protected $media_storage;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StorageInterface $syncStorage,
    protected UrlResolverInterface $urlResolver,
  ) {
    $this->media_storage = $this->entityTypeManager->getStorage('media');
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
  public function migrateMedia(array &$sandbox): void {
    if (empty($sandbox)) {
      $this->setup();

      $sandbox['mids'] = $this->media_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', 'video')
        ->execute();
      $sandbox['max'] = count($sandbox['mids']);
    }

    $mids = array_splice($sandbox['mids'], 0, 100);
    foreach ($this->media_storage->loadMultiple($mids) as $media) {
      assert($media instanceof MediaInterface);
      $url = trim($media->get('video')->getString());
      $url = $this->staticMapUrl($url);

      if (!$this->isSupportedUrl($url)) {
        $media->delete();
        continue;
      }

      // Delete old media, save new one.
      $new_media = $this->media_storage->create([
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
      'field_storage_config' => [
        'field.storage.media.media_oembed_video',
      ],
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

  /**
   *
   */
  public function migrateParagraphs(array &$sandbox): void {
    $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    if (empty($sandbox)) {
      // $this->setupParagraphs();
      $paragraphs = $paragraph_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'video')
        ->execute();

      foreach ($paragraphs as $paragraph) {
        $paragraphs_revisions[] = $paragraph->allRevisions();
      }

      $sandbox['paragraphs'] = $paragraphs;
      $sandbox['paragraphs_revisions'] = $paragraphs_revisions;
    }

    $paragraphs_ids = array_splice($sandbox['paragraphs'], 0, 100);
    $revision_ids = array_splice($sandbox['paragraphs_revisions'], 0, 100);
    $this->update_remote_video_fields($paragraph_storage->loadMultiple($paragraphs_ids));
    $this->update_remote_video_fields($paragraph_storage->loadMultipleRevisions($revision_ids));

    $sandbox['#finished'] = (int) empty($sandbox['paragraphs_revisions']);
  }

  /**
   *
   */
  protected function update_remote_video_fields($items): void {
    foreach ($items as $paragraph) {
      assert($paragraph instanceof ParagraphInterface);
      $url = trim($paragraph->get('field_paragraph_video_url')->getString());

      if ($this->isSupportedUrl($url)) {
        $this->upsertMediaEntity($url, $paragraph);
      }
    }
  }

  /**
   *
   */
  protected function upsertMediaEntity(string $url, ParagraphInterface $paragraph): void {
    $remote_video = $this->media_storage->loadByProperties(
      [
        'bundle' => 'remote_video',
        'field_media_image.uri' => $url,
      ]
    );

    if (!empty($remote_video)) {
      $existing_remote_video = array_shift($remote_video);
      $remote_video_id = $existing_remote_video->id();
    }
    else {
      $new_remote_video = $this->media_storage->create([
        'bundle' => 'remote_video',
        'media_oembed_video' => $url,
      ]);
      $remote_video_id = $new_remote_video->id();
    }
    $paragraph->set('field_remote_video', [
      'target_id' => $remote_video_id,
    ]);
    $paragraph->save();
  }

  /**
   * Setup everything we need to start the migration.
   */
  protected function setupParagraphs(): void {
    // Create new media type and field storages/configs from sync storage.
    $sync_storage = $this->syncStorage;
    $config_entities = [
      'field_storage_config' => [
        'field.storage.paragraph.remote_video',
      ],
      'field_config' => [
        'field.field.paragraph.video.remote_video',
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

}
