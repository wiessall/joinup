<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Update;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Shared machinery for turning legacy inline images into media embeds.
 *
 * Two converters run over the same fields and produce the same `<drupal-media>`
 * output; they differ only in where the image bytes come from. Everything that
 * does not depend on that difference lives here: which fields are searched, how
 * a group and a media entity are resolved, and how an embed is lifted out of
 * the paragraph the original `<img>` sat in.
 *
 * The using class is expected to hold an `$entityTypeManager` property.
 *
 * @see \Drupal\joinup_core\Update\InlineImageConverter
 * @see \Drupal\joinup_core\Update\Base64ImageConverter
 */
trait LegacyImageEmbedTrait {

  /**
   * The fields searched for legacy image markup, per entity type.
   */
  protected const array FIELDS = [
    'node' => ['body', 'description', 'field_event_agenda'],
    'paragraph' => ['field_body'],
    'comment' => ['field_body'],
  ];

  /**
   * The text format whose stored `<img src>` markup is broken on output.
   */
  protected const string FORMAT = 'content_editor';

  /**
   * The media embed view mode for converted images.
   *
   * Legacy inline images rendered full width; this is the closest view mode
   * allowed by the `media_embed` filter settings.
   */
  protected const string VIEW_MODE = 'media_library_full_width';

  /**
   * Collects every entity whose `content_editor` markup contains a needle.
   *
   * Orphaned paragraphs are excluded: rows whose `parent_id` still points at a
   * node but which no current revision references. They are neither rendered
   * nor loadable in the editor, so they are not broken and not at risk.
   *
   * @param string $needle
   *   The substring the stored markup must contain.
   *
   * @return array[]
   *   A list of ['entity_type' => string, 'id' => int|string] work items.
   */
  protected function collectEntities(string $needle): array {
    $items = [];
    foreach (self::FIELDS as $entity_type_id => $fields) {
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        continue;
      }
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $ids = [];
      foreach ($fields as $field) {
        $query = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition("{$field}.format", self::FORMAT)
          ->condition("{$field}.value", $needle, 'CONTAINS');
        foreach ($query->execute() as $id) {
          $ids[$id] = $id;
        }
      }
      foreach ($ids as $id) {
        if ($entity_type_id === 'paragraph') {
          $paragraph = $storage->load($id);
          if (!$paragraph instanceof ParagraphInterface || !$this->isLive($paragraph)) {
            continue;
          }
        }
        $items[] = ['entity_type' => $entity_type_id, 'id' => $id];
      }
    }
    return $items;
  }

  /**
   * Gets the `media:image` wrapping a file within a group, or creates it.
   *
   * The lookup is scoped to the group: media are group content, so reusing one
   * from another group would leak it across group boundaries. Group-less media
   * are likewise only ever reused by other group-less embeds, which is why the
   * absence of a group has to be matched explicitly rather than ignored.
   *
   * @param \Drupal\file\FileInterface $file
   *   The image file to wrap.
   * @param string $alt
   *   The alt text carried by the original markup, if any.
   * @param int|string|null $group_id
   *   The group the media should belong to, or NULL for none.
   * @param int $uid
   *   The owner to attribute a created media to.
   * @param int[] $stats
   *   Counters, updated by reference.
   *
   * @return \Drupal\media\MediaInterface
   *   The existing or newly created media entity.
   */
  protected function getOrCreateMedia(FileInterface $file, string $alt, int|string|NULL $group_id, int $uid, array &$stats): MediaInterface {
    $storage = $this->entityTypeManager->getStorage('media');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', 'image')
      ->condition('image.target_id', $file->id())
      ->range(0, 1);
    if ($group_id === NULL) {
      $query->notExists(OgGroupAudienceHelperInterface::DEFAULT_FIELD);
    }
    else {
      $query->condition(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $group_id);
    }
    $existing = $query->execute();
    if ($existing) {
      /** @var \Drupal\media\MediaInterface $media */
      $media = $storage->load(reset($existing));
      return $media;
    }

    // The referenced file must be permanent, or file garbage collection could
    // reap it out from under the new media entity.
    if (!$file->isPermanent()) {
      $file->setPermanent();
      $file->save();
    }

    $values = [
      'bundle' => 'image',
      'name' => $file->getFilename(),
      'status' => 1,
      // Alt is required on the image field; fall back to the file name.
      'image' => [
        'target_id' => $file->id(),
        'alt' => $alt !== '' ? $alt : $file->getFilename(),
      ],
    ];
    if ($group_id !== NULL) {
      $values[OgGroupAudienceHelperInterface::DEFAULT_FIELD] = $group_id;
    }
    /** @var \Drupal\media\MediaInterface $media */
    $media = $storage->create($values);
    if ($uid) {
      $media->setOwnerId($uid);
    }
    $media->setSyncing(TRUE);
    $media->save();
    $stats['media_created']++;

    return $media;
  }

  /**
   * Resolves the group a converted image should belong to.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity holding the markup.
   *
   * @return int|string|null
   *   The group id, or NULL when the entity belongs to no group.
   */
  protected function resolveGroupId(ContentEntityInterface $entity): int|string|NULL {
    // A group is its own audience: collections and solutions own their media.
    if ($entity->getEntityTypeId() === 'node' && in_array($entity->bundle(), ['collection', 'solution'], TRUE)) {
      return $entity->id();
    }

    if ($entity->hasField(OgGroupAudienceHelperInterface::DEFAULT_FIELD)) {
      $target = $entity->get(OgGroupAudienceHelperInterface::DEFAULT_FIELD)->target_id;
      if (!empty($target)) {
        return $target;
      }
    }

    // Paragraphs and comments inherit the group of whatever hosts them.
    $host = match (TRUE) {
      $entity instanceof ParagraphInterface => $entity->getParentEntity(),
      $entity->getEntityTypeId() === 'comment' => $entity->getCommentedEntity(),
      default => NULL,
    };

    return $host instanceof ContentEntityInterface ? $this->resolveGroupId($host) : NULL;
  }

  /**
   * Returns whether a paragraph is still referenced by its parent.
   *
   * `parent_id` records lineage only: a paragraph dropped from a later revision
   * keeps pointing at the node it was created under.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to check.
   *
   * @return bool
   *   TRUE when a current revision still references the paragraph.
   */
  protected function isLive(ParagraphInterface $paragraph): bool {
    $parent = $paragraph->getParentEntity();
    if (!$parent instanceof ContentEntityInterface) {
      return FALSE;
    }
    $field = $paragraph->get('parent_field_name')->value;
    if (!$field || !$parent->hasField($field)) {
      return FALSE;
    }
    foreach ($parent->get($field) as $item) {
      if ((string) $item->target_id === (string) $paragraph->id()) {
        // A live child of a dead parent is still dead.
        return $parent instanceof ParagraphInterface ? $this->isLive($parent) : TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Moves a media embed out of the paragraph the original image sat in.
   *
   * `<drupal-media>` is replaced by block-level markup, which is not valid
   * inside a `<p>`: the parser closes the paragraph before it, and the embed
   * lands outside the flow it was written into. This is why a converted image
   * can still fail to render. The paragraph is split around the embed, and
   * dropped altogether when the image was all it held.
   *
   * @param \DOMElement $embed
   *   The `<drupal-media>` element that replaced the image.
   */
  protected function liftFromParagraph(\DOMElement $embed): void {
    $paragraph = $embed->parentNode;
    while ($paragraph instanceof \DOMElement && strtolower($paragraph->nodeName) !== 'p') {
      $paragraph = $paragraph->parentNode;
    }
    if (!$paragraph instanceof \DOMElement || $paragraph->parentNode === NULL) {
      return;
    }

    // The direct child of the paragraph whose subtree holds the embed: the
    // embed itself, unless the image sat inside further inline markup.
    $branch = $embed;
    while ($branch->parentNode !== $paragraph) {
      $branch = $branch->parentNode;
    }

    $before = [];
    $after = [];
    $passed = FALSE;
    foreach (iterator_to_array($paragraph->childNodes) as $child) {
      if ($child === $branch) {
        $passed = TRUE;
        continue;
      }
      if ($passed) {
        $after[] = $child;
      }
      else {
        $before[] = $child;
      }
    }

    // Detach the embed from whatever wrapped it. Emptied of the image, that
    // wrapper may still hold text, which stays with the preceding paragraph.
    $embed->parentNode->removeChild($embed);
    if ($branch !== $embed && $this->hasContent($branch)) {
      $before[] = $branch;
    }

    $parent = $paragraph->parentNode;
    $this->insertSlice($before, $paragraph);
    $parent->insertBefore($embed, $paragraph);
    $this->insertSlice($after, $paragraph);
    $parent->removeChild($paragraph);
  }

  /**
   * Re-inserts one side of a split paragraph, unless it holds nothing.
   *
   * @param \DOMNode[] $nodes
   *   The nodes belonging to this side of the split.
   * @param \DOMElement $paragraph
   *   The paragraph being split, used as both template and insert position.
   */
  protected function insertSlice(array $nodes, \DOMElement $paragraph): void {
    if (!$nodes) {
      return;
    }
    // A shallow clone keeps the original paragraph's attributes.
    $slice = $paragraph->cloneNode(FALSE);
    foreach ($nodes as $node) {
      $slice->appendChild($node);
    }
    if ($this->hasContent($slice)) {
      $paragraph->parentNode->insertBefore($slice, $paragraph);
    }
  }

  /**
   * Returns whether a node holds anything worth keeping.
   *
   * @param \DOMNode $node
   *   The node to inspect.
   *
   * @return bool
   *   TRUE when the node holds text or any element.
   */
  protected function hasContent(\DOMNode $node): bool {
    if (trim($node->textContent) !== '') {
      return TRUE;
    }
    return $node instanceof \DOMElement && $node->getElementsByTagName('*')->length > 0;
  }

}
