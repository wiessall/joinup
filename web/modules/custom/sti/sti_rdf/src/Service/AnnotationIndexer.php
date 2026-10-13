<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Indexes annotation nodes as EAV rows in the annotation_value table.
 *
 * Configured by sti_rdf.settings:annotation_types — a map of annotation bundle
 * names to a small descriptor that says where the target entity reference
 * lives and how to derive a collection_id.
 *
 * Example sti_rdf.settings.yml:
 *
 *   annotation_types:
 *     dah_annotation:
 *       target_field: field_solution
 *       collection_static: dah
 *     agob_annotation:
 *       target_field: field_target
 *       collection_field: field_collection
 *       collection_id_property: field_machine_name  # optional, defaults to id
 *
 * Anything not in this map is ignored on save.
 */
final class AnnotationIndexer {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns the indexer config for the given bundle, or NULL if not registered.
   */
  public function getBundleConfig(string $bundle): ?array {
    $all = $this->configFactory->get('sti_rdf.settings')->get('annotation_types') ?? [];
    return $all[$bundle] ?? NULL;
  }

  /**
   * Returns TRUE if the bundle is registered as an annotation type.
   */
  public function isAnnotationBundle(string $bundle): bool {
    return $this->getBundleConfig($bundle) !== NULL;
  }

  /**
   * Re-indexes a single annotation node.
   *
   * Clears any existing rows for this annotation, then writes fresh ones
   * derived from the current node state. Safe to call on every save.
   */
  public function index(NodeInterface $annotation): void {
    $config = $this->getBundleConfig($annotation->bundle());
    if ($config === NULL) {
      return;
    }

    $this->clearForAnnotation((int) $annotation->id());

    $targetField = $config['target_field'] ?? 'field_solution';
    if (!$annotation->hasField($targetField) || $annotation->get($targetField)->isEmpty()) {
      return;
    }
    $target = $annotation->get($targetField)->entity;
    if ($target === NULL) {
      return;
    }

    $collectionId = $this->resolveCollectionId($annotation, $config);
    if ($collectionId === NULL || $collectionId === '') {
      $this->logger->warning('Annotation @id (bundle @b) has no resolvable collection id; skipping indexing.', [
        '@id' => $annotation->id(),
        '@b' => $annotation->bundle(),
      ]);
      return;
    }

    $ignored = array_merge(
      [$targetField],
      $config['ignored_fields'] ?? [],
      // collection_field, if set, is metadata about the annotation, not a value
      // of it — don't index it as an annotation property.
      !empty($config['collection_field']) ? [$config['collection_field']] : [],
    );

    $storage = $this->entityTypeManager->getStorage('annotation_value');
    $rows = 0;

    foreach ($annotation->getFields() as $fieldName => $fieldList) {
      if (in_array($fieldName, $ignored, TRUE)) {
        continue;
      }
      // Only index real configurable fields (skip base fields like nid, uuid,
      // status, created, etc.). Convention: configurable fields start with
      // "field_".
      if (!str_starts_with($fieldName, 'field_')) {
        continue;
      }
      if ($fieldList->isEmpty()) {
        continue;
      }
      foreach ($this->extractValues($fieldList) as [$key, $label]) {
        $storage->create([
          'annotation_id' => (int) $annotation->id(),
          'collection_id' => $collectionId,
          'target_entity_type' => $target->getEntityTypeId(),
          'target_entity_id' => (int) $target->id(),
          'field_name' => $fieldName,
          'value_key' => $key,
          'value_label' => $label,
        ])->save();
        $rows++;
      }
    }

    $this->logger->debug('Indexed annotation @id into collection @c with @n EAV rows.', [
      '@id' => $annotation->id(),
      '@c' => $collectionId,
      '@n' => $rows,
    ]);
  }

  /**
   * Removes all EAV rows attached to a given annotation.
   */
  public function clearForAnnotation(int $annotationId): void {
    $storage = $this->entityTypeManager->getStorage('annotation_value');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('annotation_id', $annotationId)
      ->execute();
    if (!empty($ids)) {
      $storage->delete($storage->loadMultiple($ids));
    }
  }

  /**
   * Rebuilds the EAV table from scratch for all registered annotation bundles.
   *
   * Returns the number of annotations processed.
   */
  public function reindexAll(): int {
    $all = $this->configFactory->get('sti_rdf.settings')->get('annotation_types') ?? [];
    if (empty($all)) {
      return 0;
    }
    $bundles = array_keys($all);

    // Wipe.
    $avStorage = $this->entityTypeManager->getStorage('annotation_value');
    $existing = $avStorage->getQuery()->accessCheck(FALSE)->execute();
    if (!empty($existing)) {
      // Delete in chunks to keep memory reasonable.
      foreach (array_chunk($existing, 500) as $chunk) {
        $avStorage->delete($avStorage->loadMultiple($chunk));
      }
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $nids = $nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundles, 'IN')
      ->execute();

    $processed = 0;
    foreach (array_chunk($nids, 100) as $chunk) {
      $nodes = $nodeStorage->loadMultiple($chunk);
      foreach ($nodes as $node) {
        $this->index($node);
        $processed++;
      }
      // Drop loaded nodes from static cache to avoid memory bloat.
      $nodeStorage->resetCache($chunk);
    }
    return $processed;
  }

  /**
   * Works out the collection_id for an annotation node.
   *
   * Order of precedence:
   *   1. collection_static (a literal string in config)
   *   2. collection_field (entity ref → take a property from the referenced
   *      entity, defaulting to its id)
   *   3. fallback: the bundle name
   */
  protected function resolveCollectionId(NodeInterface $annotation, array $config): ?string {
    if (!empty($config['collection_static'])) {
      return (string) $config['collection_static'];
    }
    if (!empty($config['collection_field'])) {
      $f = $config['collection_field'];
      if ($annotation->hasField($f) && !$annotation->get($f)->isEmpty()) {
        $ref = $annotation->get($f)->entity;
        if ($ref !== NULL) {
          $prop = $config['collection_id_property'] ?? NULL;
          if ($prop !== NULL && $ref->hasField($prop) && !$ref->get($prop)->isEmpty()) {
            return (string) $ref->get($prop)->value;
          }
          return (string) $ref->id();
        }
      }
      return NULL;
    }
    return $annotation->bundle();
  }

  /**
   * Extracts (key, label) pairs from a field item list.
   *
   * Tries to cope with the common field types — entity_reference, primitive
   * scalars, dates. Unknown types fall back to whatever ->value yields.
   *
   * @return array<array{0: string, 1: string}>
   */
  protected function extractValues(FieldItemListInterface $list): array {
    $out = [];
    foreach ($list as $item) {
      // Entity references: index target_id as key, entity label as label.
      // This is what makes facets show "Climate-smart agriculture (12)"
      // rather than "127 (12)".
      if (method_exists($item, 'getDataDefinition')
          && str_starts_with($item->getDataDefinition()->getDataType(), 'field_item:entity_reference')) {
        $target = $item->entity ?? NULL;
        if ($target !== NULL) {
          $out[] = [(string) $target->id(), (string) $target->label()];
        }
        continue;
      }

      // Plain scalar.
      $value = $item->value ?? NULL;
      if ($value === NULL || $value === '') {
        continue;
      }
      $out[] = [(string) $value, (string) $value];
    }
    return $out;
  }

}
