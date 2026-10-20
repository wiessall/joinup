<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\rdf_sync\RdfSyncMapperInterface;
use Drupal\sti_rdf\StiUri;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;

/**
 * Proposes, accepts and rejects SSSOM mappings held by a collection.
 *
 * A mapping is a 'mapping' paragraph in the issuing collection's
 * field_mappings. Unpublished means proposed, and rdf_sync_published keeps it
 * out of the graph. Accepting publishes it. Rejecting publishes it negated,
 * so that the graph, and whoever proposes next, knows the two terms do not
 * match.
 *
 * A mapping's target is either a term (another collection's property or
 * value) or an ATIO property. ATIO properties are referred to by the
 * predicate atio_innovation emits, not by Drupal field: the predicate is what
 * the graph and its readers see, and it survives field renames.
 */
final class MappingCuration {

  public const BUNDLE = 'mapping';

  public const FIELD = 'field_mappings';

  public const NEGATED = 'https://w3id.org/sssom/NegatedPredicate';

  public const MANUAL = 'https://w3id.org/semapv/vocab/ManualMappingCuration';

  public const PROPOSED = 'proposed';

  public const ACCEPTED = 'accepted';

  public const REJECTED = 'rejected';

  /**
   * The bundle whose atio: predicates are mapping targets.
   */
  public const ATIO_BUNDLE = 'atio_innovation';

  /**
   * The fields a mapping is validated on.
   */
  private const FIELDS = [
    'field_mapping_subject',
    'field_mapping_relation',
    'field_mapping_object',
    'field_mapping_target_predicate',
    'field_mapping_justification',
    'field_mapping_confidence',
    'field_mapping_tool',
    'field_mapping_modifier',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RdfSyncMapperInterface $mapper,
    private readonly AccountProxyInterface $currentUser,
    private readonly SyncReaderInterface $syncReader,
  ) {}

  /**
   * Adds proposed mappings to a collection, and saves it once.
   *
   * A row the collection already holds, in any state, is skipped. That
   * includes rejected ones: a rejection is a decision, not something to be
   * proposed again.
   *
   * @param \Drupal\node\NodeInterface $collection
   *   The issuing collection.
   * @param list<array{subject: \Drupal\taxonomy\TermInterface, relation: string, object?: \Drupal\taxonomy\TermInterface, predicate?: string, justification?: string, confidence?: float, tool?: string}> $rows
   *   The mappings; relation and justification are URIs from the fields'
   *   allowed values. Each row targets either a term ('object') or an ATIO
   *   predicate ('predicate'), never both.
   *
   * @return int
   *   How many mappings were added.
   *
   * @throws \InvalidArgumentException
   *   When a term is not projected to the graph, or a row is invalid. Nothing
   *   is saved then.
   */
  public function propose(NodeInterface $collection, array $rows): int {
    if (!$collection->hasField(self::FIELD)) {
      throw new \InvalidArgumentException(sprintf('Node %d (%s) cannot hold mappings.', $collection->id(), $collection->bundle()));
    }
    $held = [];
    foreach ($this->mappings($collection) as $mapping) {
      $held[$this->key($mapping)] = TRUE;
    }

    $storage = $this->entityTypeManager->getStorage('paragraph');
    $added = 0;
    foreach ($rows as $row) {
      if (isset($row['object']) === isset($row['predicate'])) {
        throw new \InvalidArgumentException('A mapping targets either a term or an ATIO property, and exactly one of them.');
      }
      foreach (array_filter([$row['subject'], $row['object'] ?? NULL]) as $term) {
        // A term rdf_sync does not map has no URI, and the patched normalizer
        // would silently drop the triple pointing at it.
        if (!$this->mapper->isMappedEntity($term)) {
          throw new \InvalidArgumentException(sprintf('Term %d (%s) is not in the graph, so a mapping to it would be lost.', $term->id(), $term->bundle()));
        }
      }
      $mapping = $storage->create([
        'type' => self::BUNDLE,
        'status' => FALSE,
        'field_mapping_subject' => $row['subject'],
        'field_mapping_relation' => $row['relation'],
        'field_mapping_object' => $row['object'] ?? NULL,
        'field_mapping_target_predicate' => $row['predicate'] ?? NULL,
        'field_mapping_justification' => $row['justification'] ?? self::MANUAL,
        'field_mapping_confidence' => $row['confidence'] ?? NULL,
        'field_mapping_tool' => $row['tool'] ?? NULL,
        'field_mapping_author' => $this->currentUser->id(),
      ]);
      assert($mapping instanceof ParagraphInterface);
      $key = $this->key($mapping);
      if (isset($held[$key])) {
        continue;
      }
      // Only the mapping's own fields: the parent reference is filled in when
      // the collection is saved, so the paragraph as a whole is not valid yet.
      $violations = $mapping->validate()->getByFields(self::FIELDS);
      if ($violations->count() > 0) {
        $violation = $violations->get(0);
        throw new \InvalidArgumentException(sprintf('Invalid mapping, %s: %s', $violation->getPropertyPath(), $violation->getMessage()));
      }
      $held[$key] = TRUE;
      $collection->get(self::FIELD)->appendItem($mapping);
      $added++;
    }
    if ($added > 0) {
      $collection->save();
    }
    return $added;
  }

  /**
   * Accepts a mapping: it reaches the graph as stated.
   */
  public function accept(ParagraphInterface $mapping): void {
    $this->decide($mapping, NULL);
  }

  /**
   * Rejects a mapping: it reaches the graph negated.
   */
  public function reject(ParagraphInterface $mapping): void {
    $this->decide($mapping, self::NEGATED);
  }

  /**
   * Returns the mappings a collection holds, in any state.
   *
   * @return list<\Drupal\paragraphs\ParagraphInterface>
   *   The mapping paragraphs.
   */
  public function mappings(NodeInterface $collection): array {
    if (!$collection->hasField(self::FIELD)) {
      return [];
    }
    return array_values(array_filter(
      $collection->get(self::FIELD)->referencedEntities(),
      static fn($entity): bool => $entity instanceof ParagraphInterface && $entity->bundle() === self::BUNDLE,
    ));
  }

  /**
   * Returns the ATIO properties a mapping can target.
   *
   * Read from the rdf_sync mapping of atio_innovation every time, so the list
   * follows the fields: there is no copy to keep in step.
   *
   * @return array<string, string>
   *   Field labels keyed by atio: predicate URI, by label.
   */
  public function atioPredicates(): array {
    $out = [];
    foreach ($this->syncReader->fieldSyncs('node', self::ATIO_BUNDLE) as $rdf_sync_mapping) {
      if (str_starts_with($rdf_sync_mapping['predicate'], StiUri::ATIO)) {
        $out[$rdf_sync_mapping['predicate']] ??= $rdf_sync_mapping['label'];
      }
    }
    asort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
  }

  /**
   * Allowed values of field_mapping_target_predicate.
   *
   * The field config names this callback as a string, so it is static and
   * reaches the service through \Drupal: the field API offers no injection
   * point here.
   */
  public static function allowedAtioPredicates(FieldStorageDefinitionInterface $definition, ?FieldableEntityInterface $entity = NULL, ?bool &$cacheable = TRUE): array {
    // Not cached by the options module: the list changes with config.
    $cacheable = FALSE;
    return \Drupal::service('sti_rdf.mapping_curation')->atioPredicates();
  }

  /**
   * Returns mappings whose ATIO property no longer exists.
   *
   * List fields check their values only on save, so a predicate removed from
   * atio_innovation leaves its mappings pointing at nothing, unnoticed. This
   * is what the status report shows.
   *
   * @return list<\Drupal\paragraphs\ParagraphInterface>
   *   The orphaned mappings, in any state.
   */
  public function orphans(): array {
    $storage = $this->entityTypeManager->getStorage('paragraph');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->exists('field_mapping_target_predicate')
      ->execute();
    $known = $this->atioPredicates();
    return array_values(array_filter(
      $ids ? $storage->loadMultiple($ids) : [],
      static fn($mapping): bool => !isset($known[$mapping->get('field_mapping_target_predicate')->value]),
    ));
  }

  /**
   * Returns the state of a mapping: proposed, accepted or rejected.
   */
  public function state(ParagraphInterface $mapping): string {
    return match (TRUE) {
      !$mapping->isPublished() => self::PROPOSED,
      $mapping->get('field_mapping_modifier')->isEmpty() => self::ACCEPTED,
      default => self::REJECTED,
    };
  }

  /**
   * Returns a collection's claim properties, keyed by term id.
   *
   * The ird_claims process plugin files them under a root term in
   * claim_properties named after the collection's short_id.
   *
   * @return array<int, \Drupal\taxonomy\TermInterface>
   *   The property terms; empty when the collection has none yet.
   */
  public function properties(NodeInterface $collection): array {
    if (!$collection->hasField('short_id') || $collection->get('short_id')->isEmpty()) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    assert($storage instanceof TermStorageInterface);
    $roots = $storage->loadByProperties([
      'vid' => 'claim_properties',
      'name' => $collection->get('short_id')->value,
      'parent' => 0,
    ]);
    $root = reset($roots);
    if (!$root instanceof TermInterface) {
      return [];
    }
    $children = $storage->loadChildren((int) $root->id(), 'claim_properties');
    uasort($children, static fn(TermInterface $a, TermInterface $b): int => strcasecmp($a->label(), $b->label()));
    return $children;
  }

  /**
   * Records a reviewer's decision on a mapping.
   */
  private function decide(ParagraphInterface $mapping, ?string $modifier): void {
    if ($mapping->bundle() !== self::BUNDLE) {
      throw new \InvalidArgumentException(sprintf('Paragraph %d is a %s, not a mapping.', $mapping->id(), $mapping->bundle()));
    }
    $mapping->set('field_mapping_modifier', $modifier);
    $mapping->set('field_mapping_reviewer', $this->currentUser->id());
    $mapping->setPublished();
    // Keep the revision: the collection references this revision id, and a
    // new one would leave the collection showing the undecided copy.
    $mapping->setNewRevision(FALSE);
    $mapping->save();
  }

  /**
   * Identifies a mapping by subject, relation and target.
   */
  private function key(ParagraphInterface $mapping): string {
    return implode('|', [
      $mapping->get('field_mapping_subject')->target_id,
      $mapping->get('field_mapping_relation')->value,
      $mapping->get('field_mapping_object')->target_id ?? $mapping->get('field_mapping_target_predicate')->value,
    ]);
  }

}
