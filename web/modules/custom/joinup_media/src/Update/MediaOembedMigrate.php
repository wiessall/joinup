<?php

declare(strict_types=1);

namespace Drupal\joinup_media\Update;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
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
   * Migrates media video to remote_video.
   */
  public function migrateMedia(array &$sandbox): string {
    $output = [];
    $media_storage = $this->entityTypeManager->getStorage('media');

    if (empty($sandbox)) {
      $this->prepareMedia();

      $sandbox['ids'] = $media_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', 'video')
        ->execute();
      $sandbox['max'] = count($sandbox['ids']);
    }

    $ids = array_splice($sandbox['ids'], 0, 100);
    foreach ($media_storage->loadMultiple($ids) as $media) {
      assert($media instanceof MediaInterface);
      $url = trim($media->get('video')->getString());
      $url = $this->staticMapUrl($url);
      if (!$this->isSupportedUrl($url)) {
        $output[] = sprintf('Deleting media ID %s: the "%s" URL is not supported by oEmbed.', $media->id(), $url);
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

    $sandbox['#finished'] = (int) empty($sandbox['ids']);
    return implode("\n", $output);
  }

  /**
   * Prepare for the media migration.
   */
  private function prepareMedia(): void {
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
   * Migrates video paragraphs field (paragraph_video_url > remote_video).
   */
  public function migrateParagraphs(array &$sandbox): string {
    $output = [];
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $paragraph_storage */
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $media_storage = $this->entityTypeManager->getStorage('media');

    if (empty($sandbox)) {
      $this->prepareParagraphs();
      $sandbox['rids'] = $paragraph_storage->getQuery()
        ->accessCheck(FALSE)
        ->allRevisions()
        ->condition('type', 'video')
        ->execute();
      $sandbox['rids'] = array_keys($sandbox['rids']);
    }

    $rids = array_splice($sandbox['rids'], 0, 100);
    foreach ($paragraph_storage->loadMultipleRevisions($rids) as $paragraph) {
      assert($paragraph instanceof ParagraphInterface);
      $url = trim($paragraph->get('paragraph_video_url')->getString());
      if (!$url) {
        $output[] = sprintf('Deleting paragraph %s-%s: URL is empty.', $paragraph->id(), $paragraph->getRevisionId());
        $paragraph->isDefaultRevision()
          ? $paragraph->delete()
          : $paragraph_storage->deleteRevision($paragraph->getRevisionId());
        continue;
      }

      $url = $this->staticMapUrl($url);
      if (!$this->isSupportedUrl($url)) {
        $output[] = sprintf('Deleting paragraph %s-%s: the "%s" URL is not supported by oEmbed.', $paragraph->id(), $paragraph->getRevisionId(), $url);
        $paragraph->isDefaultRevision()
          ? $paragraph->delete()
          : $paragraph_storage->deleteRevision($paragraph->getRevisionId());
        continue;
      }

      // Check if a remote_video media exist, otherwise create it.
      $values = [
        'bundle' => 'remote_video',
        'media_oembed_video' => $url,
        'og_audience' => $this->getOgAudienceFromParagraph($paragraph)?->id(),
      ];
      $existing_media_video = $media_storage->loadByProperties($values);
      $media_video = $existing_media_video ? array_shift($existing_media_video) : $media_storage->create($values);

      // Empty old field, save new one, save paragraph revision.
      $paragraph->set('paragraph_video_url', NULL);
      $paragraph->set('remote_video', $media_video);
      $paragraph->setSyncing(TRUE)->save();
    }

    $sandbox['#finished'] = (int) empty($sandbox['rids']);
    return implode("\n", $output);
  }

  /**
   * Prepare for the paragraphs migration.
   */
  private function prepareParagraphs(): void {
    // Create new field storage/config from sync storage.
    $sync_storage = $this->syncStorage;
    $config_entities = [
      'field_storage_config' => ['field.storage.paragraph.remote_video'],
      'field_config' => ['field.field.paragraph.video.remote_video'],
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
   * Gets the OG group from the given paragraph.
   */
  private function getOgAudienceFromParagraph(ParagraphInterface $paragraph): ?GroupInterface {
    // Get the top-level parent entity.
    $parent_entity = $paragraph->getParentEntity();
    while ($parent_entity instanceof ParagraphInterface) {
      $parent_entity = $parent_entity->getParentEntity();
    }

    // Get the parent's OG group.
    if ($parent_entity instanceof GroupContentInterface && $parent_entity->hasGroup()) {
      return $parent_entity->getGroup();
    }

    return NULL;
  }

}
