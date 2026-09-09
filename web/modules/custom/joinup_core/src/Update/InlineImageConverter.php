<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Update;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Converts legacy inline `<img>` markup into `<drupal-media>` embeds.
 *
 * The `content_editor` format allowed `<img … src …>` until 2023-11-16, when
 * ISAICP-8015 removed the `drupalInsertImage` CKEditor 5 button. With no plugin
 * left to supply the element, core's editor validation forced `allowed_html`
 * down to `<img alt usemap>`. Two things followed:
 *
 * - every legacy inline image stopped rendering, because `filter_html` strips
 *   the `src` on output;
 * - the stored markup started being destroyed, because `<img alt usemap>` is
 *   also listed in `ckeditor5_sourceEditing.allowed_tags`, so CKEditor 5 models
 *   the element but not its `src`. Opening an affected entity and saving it
 *   writes back an `<img>` with no `src` and no file reference.
 *
 * The second point is why this runs as early as possible: every entity
 * converted is an entity that can no longer be corrupted.
 *
 * Recoverable here means the `<img>` still names a file we can find, either via
 * `data-entity-type="file" data-entity-uuid="…"` (how the old inline-image
 * widget stored them) or via a local `/sites/default/files/…` path, including
 * one written as an absolute `https://joinup.ec.europa.eu/…` URL. Where the
 * current value has already lost every `src`, the revision history is searched
 * for the last revision that still had them.
 *
 * Out of scope here, counted and logged rather than guessed at: genuinely
 * remote images, whose hosts are in many cases long dead. Base64 `data:` URIs
 * carry their own bytes and need no file to be found, so they are handled
 * separately by \Drupal\joinup_core\Update\Base64ImageConverter.
 *
 * Saving is body-only and does not touch the state field. The notification
 * subscribers do fire on every update, but they resolve a message from the
 * workflow transition being made, and a save that transitions nothing matches
 * no configured notification — so nothing is sent. Note it is that, and not
 * `setSyncing()`, which keeps them quiet: `joinup_notification` never consults
 * the syncing flag. `skip_notification` is set anyway, so that a future
 * notification keyed on something other than a transition cannot start mailing
 * people about a data migration. Entities are also saved without a new
 * revision, which keeps paragraph revision ids valid for their parents.
 */
final class InlineImageConverter implements ContainerInjectionInterface {

  use AutowireTrait;
  use LegacyImageEmbedTrait;

  /**
   * The shape of the per-run counters.
   */
  public const array STATS = [
    'entities' => 0,
    'converted' => 0,
    'media_created' => 0,
    'recovered_from_revision' => 0,
    'skipped_remote' => 0,
    'skipped_base64' => 0,
    'skipped_empty' => 0,
    'skipped_missing_file' => 0,
  ];

  /**
   * How far back to look for a revision that still holds a `src`.
   */
  protected const int MAX_REVISIONS = 50;

  /**
   * Hosts whose `/sites/default/files/…` URLs are ours.
   *
   * Legacy markup often stores a self-referential absolute URL. It also stores
   * absolute URLs to other Drupal sites, which have the same path shape, so the
   * host cannot simply be discarded: doing that would resolve a third party's
   * image to whatever local file happened to share its path.
   */
  protected const array LOCAL_HOSTS = [
    'joinup.ec.europa.eu',
    'interoperable-europe.ec.europa.eu',
    'webgate.acceptance.ec.europa.eu',
  ];

  /**
   * Prefixes of generated derivatives, mapped back to the original file.
   *
   * `styles/<name>/public/<path>` is the Drupal 7+ image-style form and
   * `imagecache/<preset>/<path>` the Drupal 6 one. Both wrap a path that is
   * itself a real file, so the original can be recovered exactly.
   */
  protected const array DERIVATIVE_PATTERNS = [
    '#^styles/[^/]+/public/#',
    '#^imagecache/[^/]+/#',
  ];

  /**
   * Attributes transplanted from a recovered revision onto the current markup.
   */
  protected const array RECOVERABLE_ATTRIBUTES = [
    'src',
    'data-entity-type',
    'data-entity-uuid',
    'data-align',
    'data-caption',
    'data-image-style',
  ];

  /**
   * The logger channel.
   */
  protected LoggerChannelInterface $logger;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('joinup_core');
  }

  /**
   * Collects every entity that still stores a `content_editor` `<img>`.
   *
   * Orphaned paragraphs are excluded: rows whose `parent_id` still points at a
   * node but which no current revision references. They are neither rendered
   * nor loadable in the editor, so they are not broken and not at risk.
   *
   * @return array[]
   *   A list of ['entity_type' => string, 'id' => int|string] work items.
   */
  public function collect(): array {
    return $this->collectEntities('<img');
  }

  /**
   * Converts the inline images of a single entity, across all translations.
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

    // The `og_audience` constraint only fires when the field is on the form,
    // so group-less media are legitimate and not a reason to skip conversion.
    $group_id = $this->resolveGroupId($entity);
    $uid = $entity instanceof EntityOwnerInterface ? (int) $entity->getOwnerId() : 0;

    $changed = FALSE;
    foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
      $translation = $entity->getTranslation($langcode);
      foreach (self::FIELDS[$entity_type_id] as $field) {
        if (!$translation->hasField($field) || $translation->get($field)->isEmpty()) {
          continue;
        }
        foreach ($translation->get($field) as $delta => $item) {
          if ($item->format !== self::FORMAT || !str_contains((string) $item->value, '<img')) {
            continue;
          }
          $value = (string) $item->value;

          // Lost sources may still be named by an older revision. Recovery runs
          // when any image is missing, since one survivor can mask the others.
          $missing = $this->countUnrecoverableImages($value);
          if ($missing > 0) {
            $recovered = $this->recoverFromRevisions($entity, $field, $langcode, (int) $delta, $value);
            if ($recovered !== NULL) {
              $regained = $missing - $this->countUnrecoverableImages($recovered);
              if ($regained > 0) {
                $stats['recovered_from_revision'] += $regained;
                $value = $recovered;
              }
            }
          }

          // Images that are still sourceless are counted by ::convertValue(),
          // which reports each one under the reason it could not be resolved.
          $new = $this->convertValue($value, $group_id, $uid, $stats);
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
      // Belt and braces: a body-only save transitions nothing, so nothing
      // matches anyway. The flag makes that explicit rather than implicit.
      $entity->skip_notification = TRUE;
      $entity->save();
      $this->logger->info('Inline images converted on @type @id: @stats', [
        '@type' => $entity_type_id,
        '@id' => $id,
        '@stats' => json_encode(array_filter($stats)),
      ]);
    }

    return $stats;
  }

  /**
   * Rewrites the `<img>` tags of a single HTML value.
   *
   * @param string $html
   *   The stored HTML.
   * @param int|string|null $group_id
   *   The group the created media should belong to, or NULL for none.
   * @param int $uid
   *   The owner to attribute created media to.
   * @param int[] $stats
   *   Counters, updated by reference.
   *
   * @return string|null
   *   The rewritten HTML, or NULL if nothing changed.
   */
  protected function convertValue(string $html, int|string|NULL $group_id, int $uid, array &$stats): ?string {
    $document = Html::load($html);
    $changed = FALSE;

    // Snapshot the node list: replaceChild() mutates it during iteration.
    foreach (iterator_to_array($document->getElementsByTagName('img')) as $img) {
      $src = trim($img->getAttribute('src'));
      $file = $this->resolveFile($img);

      if (!$file instanceof FileInterface) {
        if ($src === '') {
          $stats['skipped_empty']++;
        }
        elseif (str_starts_with($src, 'data:') || str_starts_with($src, 'image/')) {
          $stats['skipped_base64']++;
        }
        elseif ($this->localSrcToUri($src) !== NULL) {
          // A local path that no file entity claims. The bytes may well still
          // be on disk; what is missing is the file entity to reference.
          $stats['skipped_missing_file']++;
        }
        else {
          $stats['skipped_remote']++;
        }
        continue;
      }

      $media = $this->getOrCreateMedia($file, $img->getAttribute('alt'), $group_id, $uid, $stats);

      $embed = $document->createElement('drupal-media');
      $embed->setAttribute('data-entity-type', 'media');
      $embed->setAttribute('data-entity-uuid', $media->uuid());
      $embed->setAttribute('data-view-mode', self::VIEW_MODE);
      if (($alt = $img->getAttribute('alt')) !== '') {
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
   * Restores lost `src` attributes from the entity's revision history.
   *
   * Only a revision whose image count equals the current one is considered, so
   * the markup is the same content rather than an unrelated older version. Of
   * those, the revision that still names the most images wins: sources are lost
   * progressively, so the newest match is not necessarily the most complete.
   *
   * Attributes are transplanted positionally onto the current value, leaving
   * the surrounding text as it is now, and only onto images that have nothing
   * left of their own — a source that still works is never overwritten.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose revision history is searched.
   * @param string $field
   *   The field holding the markup.
   * @param string $langcode
   *   The translation being repaired.
   * @param int $delta
   *   The delta within the field being repaired.
   * @param string $current
   *   The markup as stored now, already missing the sources to restore.
   *
   * @return string|null
   *   The repaired HTML, or NULL when no revision can help.
   */
  protected function recoverFromRevisions(ContentEntityInterface $entity, string $field, string $langcode, int $delta, string $current): ?string {
    $entity_type = $entity->getEntityType();
    if (!$entity_type->isRevisionable()) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $expected = $this->countImages($current);
    $missing = $this->countUnrecoverableImages($current);

    $vids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($entity_type->getKey('id'), $entity->id())
      ->sort($entity_type->getKey('revision'), 'DESC')
      ->range(0, self::MAX_REVISIONS)
      ->execute();

    $best = NULL;
    $best_vid = NULL;
    $best_named = 0;
    foreach (array_keys($vids) as $vid) {
      $revision = $storage->loadRevision($vid);
      if (!$revision instanceof ContentEntityInterface || !$revision->hasTranslation($langcode)) {
        continue;
      }
      $item = $revision->getTranslation($langcode)->get($field)[$delta] ?? NULL;
      if ($item === NULL) {
        continue;
      }
      $candidate = (string) $item->value;
      if ($this->countImages($candidate) !== $expected) {
        continue;
      }
      $named = $expected - $this->countUnrecoverableImages($candidate);
      if ($named <= $best_named) {
        continue;
      }
      $best = $candidate;
      $best_vid = $vid;
      $best_named = $named;
      // No older revision can improve on one that still names every image.
      if ($best_named === $expected) {
        break;
      }
    }

    if ($best === NULL) {
      return NULL;
    }

    $old = Html::load($best);
    $new = Html::load($current);
    $old_images = iterator_to_array($old->getElementsByTagName('img'));
    $new_images = iterator_to_array($new->getElementsByTagName('img'));
    $restored = 0;
    foreach ($new_images as $index => $image) {
      if (!isset($old_images[$index]) || $this->imageNamesSource($image)) {
        continue;
      }
      if (!$this->imageNamesSource($old_images[$index])) {
        continue;
      }
      foreach (self::RECOVERABLE_ATTRIBUTES as $attribute) {
        if ($old_images[$index]->hasAttribute($attribute)) {
          $image->setAttribute($attribute, $old_images[$index]->getAttribute($attribute));
        }
      }
      $restored++;
    }

    if ($restored === 0) {
      return NULL;
    }

    $this->logger->notice('Recovered @count of @missing lost inline image source(s) for @type @id from revision @vid.', [
      '@count' => $restored,
      '@missing' => $missing,
      '@type' => $entity->getEntityTypeId(),
      '@id' => $entity->id(),
      '@vid' => $best_vid,
    ]);
    return Html::serialize($new);
  }

  /**
   * Resolves the file entity an `<img>` points at, if it is a local file.
   */
  protected function resolveFile(\DOMElement $img): ?FileInterface {
    // Preferred: the old inline-image widget stored the file uuid on the tag.
    if ($img->getAttribute('data-entity-type') === 'file'
      && ($uuid = $img->getAttribute('data-entity-uuid')) !== '') {
      $file = $this->entityRepository->loadEntityByUuid('file', $uuid);
      if ($file instanceof FileInterface) {
        return $file;
      }
    }

    // Fallback: a local public-files path, matched back to its file entity.
    $uri = $this->localSrcToUri(trim($img->getAttribute('src')));
    if ($uri !== NULL) {
      $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $uri]);
      if ($files) {
        return reset($files);
      }
    }

    return NULL;
  }

  /**
   * Maps a local files URL to a `public://` URI, or NULL if it is not local.
   *
   * A self-referential absolute URL such as
   * `https://joinup.ec.europa.eu/sites/default/files/…` resolves the same way
   * as the root-relative form; an absolute URL to somebody else's Drupal site
   * does not, even though the path looks identical.
   */
  protected function localSrcToUri(string $src): ?string {
    if ($src === '') {
      return NULL;
    }

    // parse_url() yields NULL when there is no host (a relative path, which is
    // ours) and FALSE when the URL is malformed (which we cannot vouch for).
    $host = parse_url($src, PHP_URL_HOST);
    if ($host === FALSE) {
      return NULL;
    }
    if (is_string($host) && !in_array(strtolower($host), self::LOCAL_HOSTS, TRUE)) {
      return NULL;
    }

    $needle = '/sites/default/files/';
    $path = preg_replace('#^https?://[^/]+#i', '', $src);
    $position = strpos($path, $needle);
    if ($position === FALSE) {
      return NULL;
    }

    // Query strings such as an image-style itok are not part of the URI.
    $path = explode('?', substr($path, $position + strlen($needle)))[0];
    $path = urldecode($path);

    // Unwrap a generated derivative to the original it was built from.
    foreach (self::DERIVATIVE_PATTERNS as $pattern) {
      $path = preg_replace($pattern, '', $path);
    }

    return $path !== '' ? 'public://' . $path : NULL;
  }

  /**
   * Counts the `<img>` tags in a value.
   */
  protected function countImages(string $html): int {
    return preg_match_all('/<img\b[^>]*>/i', $html);
  }

  /**
   * Counts the `<img>` tags that no longer name anything we could resolve.
   *
   * Counted per image rather than per value: markup that kept one working
   * image can still have lost the source of every other image beside it.
   */
  protected function countUnrecoverableImages(string $html): int {
    if (!preg_match_all('/<img\b[^>]*>/i', $html, $matches)) {
      return 0;
    }
    $count = 0;
    foreach ($matches[0] as $tag) {
      $has_src = preg_match('/\ssrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $parts)
        && trim($parts[1] ?: ($parts[2] ?? '') ?: ($parts[3] ?? '')) !== '';
      if (!$has_src && !preg_match('/data-entity-uuid\s*=\s*"[^"]+"/i', $tag)) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * Returns whether a parsed `<img>` still names something we could resolve.
   */
  protected function imageNamesSource(\DOMElement $img): bool {
    return trim($img->getAttribute('src')) !== ''
      || $img->getAttribute('data-entity-uuid') !== '';
  }

}
