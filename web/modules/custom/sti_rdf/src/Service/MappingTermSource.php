<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sti_rdf\StiUri;
use Drupal\taxonomy\TermInterface;

/**
 * Lists the terms the SKOS mapper aligns, with the URIs the graph uses.
 *
 * Two kinds of source:
 * - 'atio': the ATIO innovation fields, as the predicates their rdf_sync
 *   mapping emits (atio:readinessLevel, …), labelled with the field label.
 * - a collection: the claim properties under that collection's term in
 *   claim_properties, with the URI rdf_sync stored for each.
 *
 * URIs are read from rdf_sync_uri, never minted here: a mapping is only
 * useful if its subject is the URI the claims point at. A term with no
 * stored URI has never been synced; it is reported, not given a guessed one.
 */
final class MappingTermSource {

  public const ATIO = 'atio';

  private const PROPERTIES = 'claim_properties';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MappingReaderInterface $mappingReader,
    private readonly Connection $database,
  ) {}

  /**
   * Lists the available sources.
   *
   * @return list<array{id: string, label: string}>
   *   ATIO first, then one entry per collection with claim properties.
   */
  public function sources(): array {
    $sources = [['id' => self::ATIO, 'label' => 'ATIO innovation fields']];
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $roots = $storage->loadTree(self::PROPERTIES, 0, 1, FALSE);
    foreach ($roots as $root) {
      // Root terms with children are collections; childless roots are
      // system predicates such as sameInnovationAs.
      if ($storage->loadChildren((int) $root->tid, self::PROPERTIES)) {
        $sources[] = ['id' => (string) $root->tid, 'label' => (string) $root->name];
      }
    }
    return $sources;
  }

  /**
   * Returns one source's terms.
   *
   * @return array{meta: array{id: string, label: string}, terms: list<array{label: string, uri: string}>, unsynced: list<string>}
   *   Terms with their graph URI, and labels of terms without one.
   *
   * @throws \InvalidArgumentException
   *   When the source is unknown.
   */
  public function terms(string $source): array {
    return $source === self::ATIO ? $this->atioTerms() : $this->collectionTerms($source);
  }

  /**
   * The ATIO innovation fields, one entry per atio: predicate.
   */
  private function atioTerms(): array {
    $terms = [];
    foreach ($this->mappingReader->fieldMappings('node', 'atio_innovation') as $mapping) {
      $predicate = $mapping['predicate'];
      if (!str_starts_with($predicate, StiUri::ATIO) || isset($terms[$predicate])) {
        continue;
      }
      $terms[$predicate] = [
        'label' => $mapping['label'] ?: substr($predicate, strlen(StiUri::ATIO)),
        'uri' => $predicate,
      ];
    }
    $terms = array_values($terms);
    usort($terms, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));
    return ['meta' => ['id' => self::ATIO, 'label' => 'ATIO'], 'terms' => $terms, 'unsynced' => []];
  }

  /**
   * The claim properties of one collection.
   */
  private function collectionTerms(string $source): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $root = ctype_digit($source) ? $storage->load((int) $source) : NULL;
    if (!$root instanceof TermInterface || $root->bundle() !== self::PROPERTIES) {
      throw new \InvalidArgumentException(sprintf('Unknown mapping source "%s".', $source));
    }

    $children = $storage->loadChildren((int) $root->id(), self::PROPERTIES);
    $uris = $children ? $this->storedUris(array_keys($children)) : [];

    $terms = [];
    $unsynced = [];
    foreach ($children as $tid => $child) {
      $label = (string) $child->label();
      if (isset($uris[(int) $tid])) {
        $terms[] = ['label' => $label, 'uri' => $uris[(int) $tid]];
      }
      else {
        $unsynced[] = $label;
      }
    }
    usort($terms, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));
    sort($unsynced);
    return [
      'meta' => ['id' => $source, 'label' => (string) $root->label()],
      'terms' => $terms,
      'unsynced' => $unsynced,
    ];
  }

  /**
   * Reads stored URIs for terms.
   *
   * @param int[] $tids
   *   Term ids.
   *
   * @return array<int, string>
   *   URI keyed by term id.
   */
  private function storedUris(array $tids): array {
    if (!$this->database->schema()->tableExists('rdf_sync_uri')) {
      return [];
    }
    return array_map('strval', $this->database->select('rdf_sync_uri', 'u')
      ->fields('u', ['entity_id', 'uri'])
      ->condition('u.entity_type', 'taxonomy_term')
      ->condition('u.entity_id', $tids, 'IN')
      ->execute()
      ->fetchAllKeyed());
  }

}
