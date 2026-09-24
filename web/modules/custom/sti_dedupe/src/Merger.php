<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\joinup_group\Entity\ShareableContentInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Applies the three duplicate-resolution outcomes.
 *
 * Migration itself never merges (ADR #12): it always creates a solution per
 * incoming record, and a reviewer decides afterwards. Everything here is
 * therefore additive or reversible from the per-collection raw payload.
 */
final class Merger {

  /**
   * Paragraph fields moved from the loser to the survivor.
   */
  private const MOVED_FIELDS = ['field_claims', 'field_source_records'];

  /**
   * URI of the deliberately non-transitive sameness predicate.
   *
   * Not owl:sameAs: that asserts identity of individuals, so a reasoner would
   * merge the two titles the reviewer explicitly refused to merge, and it is
   * transitive, so one bad link contaminates a chain.
   */
  private const SAME_AS_URI = 'https://sti-portal.fao.org/ontology#sameInnovationAs';

  private const SAME_AS_LABEL = 'sameInnovationAs';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly MigrationPluginManagerInterface $migrationManager,
    private readonly DecisionStore $decisions,
    private readonly CandidateStore $candidates,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Outcome 1: the two records describe one AFSI; keep the existing one.
   *
   * The loser's claims and raw payload move to the survivor, the survivor is
   * shared into the loser's collection, the migrate map is repointed, a
   * redirect is left behind, and only then is the loser deleted.
   */
  public function keepExisting(NodeInterface $loser, NodeInterface $winner): void {
    $transaction = $this->database->startTransaction();
    try {
      $loserId = (int) $loser->id();
      $winnerId = (int) $winner->id();

      $this->moveParagraphs($loser, $winner);
      $this->shareSurvivorIntoLoserCollection($loser, $winner);
      $this->repointMigrateMap($loserId, $winnerId);
      $this->createRedirect($loserId, $winnerId);

      $this->decisions->record($loserId, $winnerId, DecisionStore::MERGED);
      $this->candidates->deleteForNode($loserId);

      $loser->delete();

      $this->loggerFactory->get('sti_dedupe')->notice(
        'Merged node @loser into @winner; claims and raw payload moved, migrate map repointed.',
        ['@loser' => $loserId, '@winner' => $winnerId]
      );
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Outcome 2: the same AFSI, but both records keep their own identity.
   *
   * Writes a sti:sameInnovationAs claim on each side, attributed to that
   * side's own collection, so the assertion carries provenance and can be
   * revoked without touching anything else.
   */
  public function keepBoth(NodeInterface $a, NodeInterface $b): void {
    $this->addSameAsClaim($a, $b);
    $this->addSameAsClaim($b, $a);

    $this->decisions->record((int) $a->id(), (int) $b->id(), DecisionStore::SAME_SEPARATE);
    $this->candidates->deletePair((int) $a->id(), (int) $b->id());
  }

  /**
   * Outcome 3: not the same AFSI.
   *
   * Recording this is what stops the pair being offered again on re-import.
   */
  public function markDifferent(NodeInterface $a, NodeInterface $b): void {
    $this->decisions->record((int) $a->id(), (int) $b->id(), DecisionStore::DIFFERENT);
    $this->candidates->deletePair((int) $a->id(), (int) $b->id());
  }

  /**
   * Re-parents the loser's paragraphs onto the survivor.
   *
   * Order matters. entity_reference_revisions is a composite relationship, so
   * deleting the loser would take its paragraphs with it. The paragraphs are
   * re-parented and attached to the survivor first, then removed from the
   * loser, and only then is the loser deleted.
   *
   * Re-parenting rather than cloning keeps paragraph ids — and therefore the
   * FieldClaim URIs minted from them — stable across a merge.
   */
  private function moveParagraphs(NodeInterface $loser, NodeInterface $winner): void {
    foreach (self::MOVED_FIELDS as $fieldName) {
      if (!$loser->hasField($fieldName) || !$winner->hasField($fieldName)) {
        continue;
      }

      $items = $winner->get($fieldName)->getValue();
      foreach ($loser->get($fieldName)->referencedEntities() as $paragraph) {
        if (!$paragraph instanceof ParagraphInterface) {
          continue;
        }
        $paragraph->set('parent_id', (string) $winner->id());
        $paragraph->set('parent_type', 'node');
        $paragraph->set('parent_field_name', $fieldName);
        $paragraph->setNewRevision(FALSE);
        $paragraph->save();

        $items[] = [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ];
      }

      $winner->set($fieldName, $items);
      $loser->set($fieldName, []);
    }

    $winner->save();
    $loser->save();
  }

  /**
   * Shares the survivor into the collection the loser belonged to.
   *
   * og_audience has cardinality 1, so a solution cannot belong to two
   * collections. Joinup's own sharing mechanism is what makes the survivor
   * appear in the other collection's listings, and it deliberately confers
   * visibility without edit rights.
   */
  private function shareSurvivorIntoLoserCollection(NodeInterface $loser, NodeInterface $winner): void {
    if (!$winner instanceof ShareableContentInterface) {
      return;
    }
    $loserCollection = $this->collectionOf($loser);
    $winnerCollection = $this->collectionOf($winner);
    if (!$loserCollection || ($winnerCollection && $winnerCollection->id() === $loserCollection->id())) {
      return;
    }

    try {
      $winner->shareWith([$loserCollection]);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('sti_dedupe')->warning(
        'Could not share node @nid into collection @collection: @message',
        [
          '@nid' => $winner->id(),
          '@collection' => $loserCollection->id(),
          '@message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * Points every migrate id map row for the loser at the survivor.
   *
   * Without this the next import finds a mapping to a deleted node, recreates
   * the duplicate and silently undoes the reviewer's decision. With it,
   * ird_claims' existing per-collection delete-and-recreate refreshes that
   * collection's claims on the merged record instead of forking it again.
   *
   * There is no entity-level API for rewriting a destination id, so this goes
   * through the id map's own table.
   */
  private function repointMigrateMap(int $loserId, int $winnerId): void {
    $tags = (array) ($this->configFactory->get('sti_dedupe.settings')->get('migration_tags') ?? []);

    try {
      // createInstancesByTag() is only on the concrete plugin manager, so the
      // tag filter is applied against each definition instead.
      $migrations = $this->migrationManager->createInstances([]);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('sti_dedupe')->error(
        'Could not load migrations to repoint id maps: @message',
        ['@message' => $e->getMessage()]
      );
      return;
    }

    foreach ($migrations as $migration) {
      try {
        $definitionTags = (array) ($migration->getPluginDefinition()['migration_tags'] ?? []);
        if ($tags && !array_intersect($tags, $definitionTags)) {
          continue;
        }

        $idMap = $migration->getIdMap();
        if (!$idMap instanceof Sql) {
          continue;
        }
        $table = $idMap->getQualifiedMapTableName();
        if (!$this->database->schema()->tableExists($table)) {
          continue;
        }
        $this->database->update($table)
          ->fields(['destid1' => $winnerId])
          ->condition('destid1', $loserId)
          ->execute();
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('sti_dedupe')->warning(
          'Could not repoint the id map of migration @id: @message',
          ['@id' => $migration->id(), '@message' => $e->getMessage()]
        );
      }
    }
  }

  /**
   * Leaves a permanent redirect from the deleted node to the survivor.
   */
  private function createRedirect(int $loserId, int $winnerId): void {
    if (!$this->moduleHandler->moduleExists('redirect')) {
      return;
    }
    try {
      $this->entityTypeManager->getStorage('redirect')->create([
        'redirect_source' => ['path' => 'node/' . $loserId, 'query' => []],
        'redirect_redirect' => ['uri' => 'internal:/node/' . $winnerId],
        'language' => 'und',
        'status_code' => 301,
      ])->save();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('sti_dedupe')->warning(
        'Could not create a redirect from node @loser to @winner: @message',
        ['@loser' => $loserId, '@winner' => $winnerId, '@message' => $e->getMessage()]
      );
    }
  }

  /**
   * Adds one sti:sameInnovationAs claim to $subject, pointing at $object.
   */
  private function addSameAsClaim(NodeInterface $subject, NodeInterface $object): void {
    if (!$subject->hasField('field_claims')) {
      return;
    }
    $collection = $this->collectionOf($subject);
    if (!$collection) {
      return;
    }

    $property = $this->sameAsPropertyTerm($collection);
    if (!$property) {
      return;
    }

    $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => 'claims',
      'field_claimed_by' => ['target_id' => $collection->id()],
      'field_claim_property' => ['target_id' => $property->id()],
      'field_claimed_at' => $this->time->getRequestTime(),
    ]);

    // field_claim_ref_value only targets taxonomy terms, so a claim whose
    // object is another solution needs field_claim_ref_node, which this module
    // installs. Fall back to the node's URI as a literal if it is missing.
    if ($paragraph->hasField('field_claim_ref_node')) {
      $paragraph->set('field_claim_ref_node', ['target_id' => $object->id()]);
    }
    else {
      $paragraph->set('field_claim_literal_value', [
        'value' => $object->toUrl('canonical', ['absolute' => TRUE])->toString(),
      ]);
    }
    $paragraph->save();

    $items = $subject->get('field_claims')->getValue();
    $items[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    $subject->set('field_claims', $items);

    $this->mirrorToRelatedSolutions($subject, $object);

    $subject->save();
  }

  /**
   * Mirrors a sameness link into Joinup's own related_solutions field.
   *
   * related_solutions already exists on the solution bundle and is rendered by
   * the stock tile, teaser, carousel and group header displays, so writing to
   * it makes the link visible with no new display code. It carries no
   * attribution and has no rdf_sync mapping, which is why it is a projection
   * of the claim rather than the record of it: the claim stays the write model
   * and this is the read model, the same split the core fields use.
   *
   * The caller saves the node, so this only stages the value.
   */
  private function mirrorToRelatedSolutions(NodeInterface $subject, NodeInterface $object): void {
    if (!$subject->hasField('related_solutions')) {
      return;
    }

    $items = $subject->get('related_solutions')->getValue();
    foreach ($items as $item) {
      if ((int) ($item['target_id'] ?? 0) === (int) $object->id()) {
        return;
      }
    }

    $items[] = ['target_id' => $object->id()];
    $subject->set('related_solutions', $items);
  }

  /**
   * Gets or creates the sameInnovationAs term inside a collection's namespace.
   *
   * claim_properties is hierarchical with the collection as the parent term,
   * mirroring how ird_claims builds it.
   */
  private function sameAsPropertyTerm(NodeInterface $collection): ?TermInterface {
    $slug = (string) ($collection->get('short_id')->value ?? '');
    if ($slug === '') {
      return NULL;
    }

    $parent = $this->termByName('claim_properties', $slug, 0);
    if (!$parent) {
      $parent = $this->createTerm('claim_properties', $slug, 0);
    }

    $term = $this->termByName('claim_properties', self::SAME_AS_LABEL, (int) $parent->id());
    if (!$term) {
      $term = $this->createTerm('claim_properties', self::SAME_AS_LABEL, (int) $parent->id());
    }

    if ($term->hasField('field_property_uri') && $term->get('field_property_uri')->isEmpty()) {
      $term->set('field_property_uri', self::SAME_AS_URI);
      $term->save();
    }

    return $term;
  }

  /**
   * Loads a term by name within a vocabulary and parent.
   */
  private function termByName(string $vocabulary, string $name, int $parent): ?TermInterface {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $vocabulary)
      ->condition('name', $name)
      ->condition('parent', $parent)
      ->range(0, 1)
      ->execute();

    if (!$ids) {
      return NULL;
    }
    $term = $storage->load(reset($ids));
    return $term instanceof TermInterface ? $term : NULL;
  }

  /**
   * Creates a term in a vocabulary under a parent.
   */
  private function createTerm(string $vocabulary, string $name, int $parent): TermInterface {
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->create([
      'vid' => $vocabulary,
      'name' => $name,
      'parent' => $parent,
    ]);
    $term->save();
    assert($term instanceof TermInterface);
    return $term;
  }

  /**
   * Returns the collection a solution belongs to.
   */
  private function collectionOf(NodeInterface $solution): ?NodeInterface {
    if (!$solution->hasField('og_audience') || $solution->get('og_audience')->isEmpty()) {
      return NULL;
    }
    $collection = $solution->get('og_audience')->entity;
    return $collection instanceof NodeInterface ? $collection : NULL;
  }

}
