<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Update;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Converts inline base64 `data:` images into `<drupal-media>` embeds.
 *
 * These are the images \Drupal\joinup_core\Update\InlineImageConverter counts
 * and leaves alone: an `<img>` whose `src` carries the image bytes inline
 * rather than naming a file. They are broken in exactly the same way as every
 * other legacy inline image — `filter_html` strips the `src` on output, and
 * CKEditor 5 drops it on the next save — but they are the only ones that need
 * no file to be found, because the bytes are right there in the markup.
 *
 * Two things make them worth a converter of their own rather than a branch in
 * the other one:
 *
 * - the bytes have to be written to disk and given a file entity before the
 *   shared media handling can take over, and that write has to be deduplicated
 *   by content, since the same payload is repeated across translations;
 * - a large share of them are not content at all but 15×15 and 16×16 spacers
 *   and social icons from Word and webmail pastes, which are dropped rather
 *   than promoted into the media library.
 *
 * Some stored values have lost the `data:` scheme prefix and begin straight at
 * `image/png;base64,…`. That is the only damage `filter_html` has done to them;
 * the payload behind it decodes intact, so both spellings are accepted.
 *
 * Deduplication is by content hash, expressed through the destination URI: the
 * same bytes always resolve to the same `public://` path, so a payload repeated
 * across 24 translations of one paragraph is written once and reused. This also
 * makes the hook restartable — a second run finds the file already there.
 *
 * Saving follows the same rules as the sibling converter: no new revision, no
 * state change, `skip_notification` set.
 *
 * @see \Drupal\joinup_core\Update\InlineImageConverter
 */
final class Base64ImageConverter implements ContainerInjectionInterface {

  use AutowireTrait;
  use LegacyImageEmbedTrait;

  /**
   * The shape of the per-run counters.
   */
  public const array STATS = [
    'entities' => 0,
    'converted' => 0,
    'media_created' => 0,
    'files_created' => 0,
    'dropped_icon' => 0,
    'skipped_undecodable' => 0,
    'skipped_unsupported' => 0,
  ];

  /**
   * Where the decoded payloads are written.
   */
  protected const string DIRECTORY = 'public://inline-images/base64';

  /**
   * Image types that may be written to disk, mapped to their extension.
   *
   * SVG is deliberately absent: it is not a raster image, it can carry script,
   * and no stored payload uses it.
   */
  protected const array EXTENSIONS = [
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_BMP => 'bmp',
    IMAGETYPE_WEBP => 'webp',
  ];

  /**
   * Below this size a payload is treated as a spacer or icon, not content.
   *
   * Every payload in the corpus is either under 1 KB — 15×15 and 16×16 bullets,
   * arrows and social icons, two of which alone account for 40 of the 98 tags —
   * or over 1.9 KB and a real image. The gap is wide enough that a single
   * threshold separates them without cutting anything close.
   */
  protected const int MIN_BYTES = 1024;

  /**
   * Payloads no larger than this in both dimensions are icons regardless.
   *
   * A backstop for a well-compressed icon that clears ::MIN_BYTES: nothing this
   * small is being embedded as content.
   */
  protected const int MAX_ICON_DIMENSION = 32;

  /**
   * Matches an inline base64 image source, with or without the `data:` prefix.
   */
  protected const string SOURCE_PATTERN = '#^(?:data:)?(image/[a-z0-9.+-]+);base64,(.*)$#is';

  /**
   * The logger channel.
   */
  protected LoggerChannelInterface $logger;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileRepositoryInterface $fileRepository,
    protected FileSystemInterface $fileSystem,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('joinup_core');
  }

  /**
   * Collects every entity that still stores an inline base64 image.
   *
   * The needle also matches a base64 payload sitting in a CSS
   * `background-image: url(data:…)` inside a `style` attribute, which is not an
   * image tag and is left alone — `filter_html` strips `style` anyway. Those
   * entities are collected and then found to need no change, which is why the
   * processed count runs slightly ahead of the changed one.
   *
   * @return array[]
   *   A list of ['entity_type' => string, 'id' => int|string] work items.
   */
  public function collect(): array {
    return $this->collectEntities('base64,');
  }

  /**
   * Converts the base64 images of a single entity, across all translations.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param int|string $id
   *   The entity id.
   *
   * @return int[]
   *   Per-outcome counters, keyed as in ::STATS.
   */
  public function convert(string $entity_type_id, int|string $id): array {
    $stats = self::STATS;

    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
    if (!$entity instanceof ContentEntityInterface) {
      return $stats;
    }

    $group_id = $this->resolveGroupId($entity);
    $uid = $entity instanceof EntityOwnerInterface ? (int) $entity->getOwnerId() : 0;

    $changed = FALSE;
    foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
      $translation = $entity->getTranslation($langcode);
      foreach (self::FIELDS[$entity_type_id] as $field) {
        if (!$translation->hasField($field) || $translation->get($field)->isEmpty()) {
          continue;
        }
        foreach ($translation->get($field) as $item) {
          if ($item->format !== self::FORMAT || !str_contains((string) $item->value, 'base64,')) {
            continue;
          }
          $new = $this->convertValue((string) $item->value, $group_id, $uid, $this->hostLabel($entity), $stats);
          if ($new !== NULL) {
            $item->value = $new;
            $changed = TRUE;
          }
        }
      }
    }

    if ($changed) {
      $stats['entities']++;
      $entity->setSyncing(TRUE);
      if ($entity->getEntityType()->isRevisionable()) {
        $entity->setNewRevision(FALSE);
      }
      $entity->skip_notification = TRUE;
      $entity->save();
      $this->logger->info('Base64 images converted on @type @id: @stats', [
        '@type' => $entity_type_id,
        '@id' => $id,
        '@stats' => json_encode(array_filter($stats)),
      ]);
    }

    return $stats;
  }

  /**
   * Rewrites the inline base64 images of a single HTML value.
   *
   * @param string $html
   *   The stored HTML.
   * @param int|string|null $group_id
   *   The group the created media should belong to, or NULL for none.
   * @param int $uid
   *   The owner to attribute created media to.
   * @param string $fallback_alt
   *   Alt text for images that carry none of their own.
   * @param int[] $stats
   *   Counters, updated by reference.
   *
   * @return string|null
   *   The rewritten HTML, or NULL if nothing changed.
   */
  protected function convertValue(string $html, int|string|NULL $group_id, int $uid, string $fallback_alt, array &$stats): ?string {
    $document = Html::load($html);
    $changed = FALSE;

    // Snapshot the node list: the tags are replaced while iterating over it.
    foreach (iterator_to_array($document->getElementsByTagName('img')) as $img) {
      $payload = $this->decode($img->getAttribute('src'));
      if ($payload === NULL) {
        continue;
      }

      $info = @getimagesizefromstring($payload);
      if ($info === FALSE) {
        // Base64 that does not decode to an image the toolkit recognises. Left
        // in place: there is nothing to write, and dropping it would destroy
        // the only copy of whatever it is.
        $stats['skipped_undecodable']++;
        continue;
      }
      if (!isset(self::EXTENSIONS[$info[2]])) {
        $stats['skipped_unsupported']++;
        continue;
      }

      if ($this->isIcon($payload, (int) $info[0], (int) $info[1])) {
        $this->dropImage($img);
        $stats['dropped_icon']++;
        $changed = TRUE;
        continue;
      }

      $file = $this->getOrCreateFile($payload, self::EXTENSIONS[$info[2]], $stats);
      if (!$file instanceof FileInterface) {
        $stats['skipped_undecodable']++;
        continue;
      }

      // A data URI carries no filename, so the media falls back to a hashed
      // one — meaningless to a screen reader, and alt is required here. The
      // page the image was embedded in is the only description available.
      $alt = $img->getAttribute('alt');
      $media = $this->getOrCreateMedia($file, $alt !== '' ? $alt : $fallback_alt, $group_id, $uid, $stats);

      $embed = $document->createElement('drupal-media');
      $embed->setAttribute('data-entity-type', 'media');
      $embed->setAttribute('data-entity-uuid', $media->uuid());
      $embed->setAttribute('data-view-mode', self::VIEW_MODE);
      if ($alt !== '') {
        $embed->setAttribute('alt', $alt);
      }
      foreach (['data-align', 'data-caption'] as $carry) {
        if ($img->hasAttribute($carry)) {
          $embed->setAttribute($carry, $img->getAttribute($carry));
        }
      }

      $img->parentNode->replaceChild($embed, $img);
      $this->liftFromParagraph($embed);
      $stats['converted']++;
      $changed = TRUE;
    }

    return $changed ? Html::serialize($document) : NULL;
  }

  /**
   * Decodes an inline base64 source into raw bytes.
   *
   * @param string $src
   *   The `src` attribute value.
   *
   * @return string|null
   *   The decoded bytes, or NULL when the source is not an inline base64
   *   image or the payload is not valid base64.
   */
  protected function decode(string $src): ?string {
    $src = trim($src);
    if ($src === '' || !preg_match(self::SOURCE_PATTERN, $src, $parts)) {
      return NULL;
    }
    // Editors wrap long payloads, and the entity-encoded newlines survive the
    // round trip into the attribute value.
    $payload = preg_replace('/\s+/', '', $parts[2]);
    if ($payload === '') {
      return NULL;
    }
    $decoded = base64_decode($payload, TRUE);

    return $decoded === FALSE || $decoded === '' ? NULL : $decoded;
  }

  /**
   * Returns whether a payload is a spacer or icon rather than content.
   *
   * @param string $payload
   *   The decoded bytes.
   * @param int $width
   *   The image width in pixels.
   * @param int $height
   *   The image height in pixels.
   *
   * @return bool
   *   TRUE when the image should be dropped rather than converted.
   */
  protected function isIcon(string $payload, int $width, int $height): bool {
    return strlen($payload) < self::MIN_BYTES
      || ($width <= self::MAX_ICON_DIMENSION && $height <= self::MAX_ICON_DIMENSION);
  }

  /**
   * Removes a dropped image, and the paragraph it was alone in.
   *
   * @param \DOMElement $img
   *   The image element to remove.
   */
  protected function dropImage(\DOMElement $img): void {
    $parent = $img->parentNode;
    $img->parentNode->removeChild($img);
    // An icon on a line of its own leaves an empty paragraph behind, which
    // renders as a stray gap.
    while ($parent instanceof \DOMElement && $parent->parentNode !== NULL && !$this->hasContent($parent)) {
      $grandparent = $parent->parentNode;
      $grandparent->removeChild($parent);
      $parent = $grandparent;
    }
  }

  /**
   * Returns the label of the content the markup ultimately belongs to.
   *
   * Paragraphs and comments have no label worth showing, so the walk continues
   * up to whatever hosts them.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity holding the markup.
   *
   * @return string
   *   The host label.
   */
  protected function hostLabel(ContentEntityInterface $entity): string {
    $host = match (TRUE) {
      $entity instanceof ParagraphInterface => $entity->getParentEntity(),
      $entity->getEntityTypeId() === 'comment' => $entity->getCommentedEntity(),
      default => NULL,
    };

    return $host instanceof ContentEntityInterface
      ? $this->hostLabel($host)
      : (string) $entity->label();
  }

  /**
   * Gets the file holding a payload, writing it out the first time.
   *
   * The destination is derived from the content hash, so identical payloads —
   * the same image repeated across translations, or across entities — resolve
   * to one path and one file entity.
   *
   * @param string $payload
   *   The decoded bytes.
   * @param string $extension
   *   The file extension for the detected image type.
   * @param int[] $stats
   *   Counters, updated by reference.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL when the write failed.
   */
  protected function getOrCreateFile(string $payload, string $extension, array &$stats): ?FileInterface {
    $uri = sprintf('%s/inline-%s.%s', self::DIRECTORY, substr(hash('sha256', $payload), 0, 16), $extension);

    $storage = $this->entityTypeManager->getStorage('file');
    $existing = $storage->loadByProperties(['uri' => $uri]);
    if ($existing) {
      /** @var \Drupal\file\FileInterface $file */
      $file = reset($existing);
      return $file;
    }

    // prepareDirectory() takes its argument by reference, so the constant has
    // to be handed over through a variable.
    $directory = self::DIRECTORY;
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $this->logger->error('Could not prepare @dir for inline base64 images.', ['@dir' => self::DIRECTORY]);
      return NULL;
    }

    try {
      // A file left on disk by an interrupted run is rewritten rather than
      // renamed, so the URI keeps matching the content hash.
      $file = $this->fileRepository->writeData($payload, $uri, FileExists::Replace);
    }
    catch (\Exception $e) {
      $this->logger->error('Could not write inline base64 image to @uri: @message', [
        '@uri' => $uri,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }

    $file->setPermanent();
    $file->save();
    $stats['files_created']++;

    return $file;
  }

}
