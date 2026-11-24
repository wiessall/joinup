<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\sti_dedupe\Exception\InvariantViolationException;
use Drupal\sti_rdf\StiUri;
use Drupal\taxonomy\TermInterface;

/**
 * Writes and reads sameInnovationAs claims on solutions.
 *
 * A claim is one collection's statement about its own record: the subject
 * is a solution in the deciding collection, the object
 * (field_claim_ref_node) is the solution it is the same as, and the claim is
 * attributed to that collection, like every other claim on the subject. The
 * object's record is never written. So claims are one-way, and code reading
 * them follows both directions (linkedTo()).
 *
 * The claim is mirrored into the subject's related_solutions so the stock
 * Joinup displays show the link.
 *
 * In the graph:
 *   <solution> sti:hasFieldClaim ?c .
 *   ?c sti:claimProperty <sameInnovationAs term> ;
 *      sti:claimRefValue <other solution> ; sti:claimedBy <collection> .
 *
 * The property term is one root-level term in claim_properties, shared by
 * every collection. ird_claims treats root-level properties as not its own
 * and keeps those claims on re-import; collection properties sit under
 * their collection's term.
 */
final class SameAsClaims {

  /**
   * Name of the system property term.
   */
  public const PROPERTY_NAME = 'sameInnovationAs';

  /**
   * URI the property term is given on creation.
   */
  public const PROPERTY_URI = StiUri::STI . 'sameInnovationAs';

  /**
   * The computed URI field rdf_sync provides on claim_properties.
   *
   * Only present while the vocabulary has an rdf_sync mapping.
   */
  private const URI_FIELD = 'claim_property_uri';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly AccountProxyInterface $currentUser,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Writes "subject is the same innovation as object" on the subject.
   *
   * Attributed to the subject's collection. Idempotent: an existing claim is
   * not written again. Checked against storage after saving.
   *
   * @return bool
   *   TRUE when a claim was added, FALSE when it already existed.
   *
   * @throws \LogicException
   *   When the claim fields are missing or the subject has no collection.
   * @throws \Drupal\sti_dedupe\Exception\InvariantViolationException
   *   When the saved subject does not carry the claim.
   */
  public function claim(NodeInterface $subject, NodeInterface $object): bool {
    $this->assertWritable($subject);
    $property = $this->property();
    $objectId = (int) $object->id();
    if (in_array($objectId, $this->objectsOf($subject, (int) $property->id()), TRUE)) {
      return FALSE;
    }

    $subject->get(ContentModel::CLAIMS)->appendItem($this->entityTypeManager->getStorage('paragraph')->create([
      'type' => ContentModel::CLAIM,
      ContentModel::CLAIMED_BY => (int) $subject->get(ContentModel::AUDIENCE)->target_id,
      ContentModel::CLAIM_PROPERTY => $property->id(),
      ContentModel::CLAIM_REF_NODE => $objectId,
      ContentModel::CLAIMED_AT => $this->time->getRequestTime(),
    ]));
    $this->mirror($subject, $objectId);
    $subject->setNewRevision(TRUE);
    $subject->setRevisionUserId((int) $this->currentUser->id());
    $subject->setRevisionLogMessage(sprintf('Duplicate review: same innovation as solution %d.', $objectId));
    $subject->save();

    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$subject->id()]);
    $stored = $storage->load($subject->id());
    if (!$stored instanceof NodeInterface || !in_array($objectId, $this->objectsOf($stored, (int) $property->id()), TRUE)) {
      $message = sprintf('sameInnovationAs claim %d→%d missing after writing it.', $subject->id(), $objectId);
      $this->logger->error($message);
      throw new InvariantViolationException($message);
    }
    return TRUE;
  }

  /**
   * Returns the solutions linked to any of the given ones by a claim.
   *
   * Both directions: solutions the given ones claim, and solutions claiming
   * the given ones. One hop only; the given ids are left out.
   *
   * @param int[] $nids
   *   Solution node ids.
   *
   * @return int[]
   *   Solution node ids, ascending.
   */
  public function linkedTo(array $nids): array {
    $propertyId = $this->findProperty()?->id();
    if (!$nids || $propertyId === NULL) {
      return [];
    }
    $propertyId = (int) $propertyId;
    $storage = $this->entityTypeManager->getStorage('node');
    $linked = [];

    foreach ($storage->loadMultiple($nids) as $solution) {
      if ($solution instanceof NodeInterface) {
        array_push($linked, ...$this->objectsOf($solution, $propertyId));
      }
    }

    // Incoming: the query narrows by object, objectsOf() confirms the
    // property on the same paragraph.
    $subjects = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ContentModel::SOLUTION)
      ->condition(ContentModel::CLAIMS . '.entity:paragraph.' . ContentModel::CLAIM_REF_NODE, array_values($nids), 'IN')
      ->execute();
    foreach ($storage->loadMultiple($subjects) as $subject) {
      if ($subject instanceof NodeInterface && array_intersect($nids, $this->objectsOf($subject, $propertyId))) {
        $linked[] = (int) $subject->id();
      }
    }

    $linked = array_values(array_diff(array_unique($linked), array_map('intval', $nids)));
    sort($linked);
    return $linked;
  }

  /**
   * Returns the ids of the solutions a solution claims to be the same as.
   *
   * @return int[]
   *   Solution node ids, ascending.
   */
  public function objectsOf(NodeInterface $solution, ?int $propertyId = NULL): array {
    $propertyId ??= $this->findProperty()?->id();
    if ($propertyId === NULL || !$solution->hasField(ContentModel::CLAIMS)) {
      return [];
    }
    $objects = [];
    foreach ($solution->get(ContentModel::CLAIMS) as $item) {
      $claim = $item->entity;
      if ($claim instanceof ParagraphInterface
        && $claim->hasField(ContentModel::CLAIM_REF_NODE)
        && (int) $claim->get(ContentModel::CLAIM_PROPERTY)->target_id === (int) $propertyId
        && !$claim->get(ContentModel::CLAIM_REF_NODE)->isEmpty()) {
        $objects[] = (int) $claim->get(ContentModel::CLAIM_REF_NODE)->target_id;
      }
    }
    $objects = array_values(array_unique($objects));
    sort($objects);
    return $objects;
  }

  /**
   * Returns the system property term, creating it at the root if needed.
   */
  public function property(): TermInterface {
    $term = $this->findProperty();
    if ($term !== NULL) {
      return $term;
    }

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->create([
      'vid' => ContentModel::CLAIM_PROPERTIES,
      'name' => self::PROPERTY_NAME,
      'parent' => [0],
    ]);
    assert($term instanceof TermInterface);
    // Set before the first save: rdf_sync mints a URI on save when none is
    // given, and a minted one cannot be replaced without rebuilding the
    // graph.
    if ($term->hasField(self::URI_FIELD)) {
      $term->set(self::URI_FIELD, self::PROPERTY_URI);
    }
    $term->save();

    $stored = $this->storedUri((int) $term->id());
    if ($stored !== NULL && $stored !== self::PROPERTY_URI) {
      // Handled: claims still work through the term id; only the graph shows
      // a minted URI instead of the vocabulary one.
      $this->logger->warning('The @name property term got the URI @stored instead of @uri.', [
        '@name' => self::PROPERTY_NAME,
        '@stored' => $stored,
        '@uri' => self::PROPERTY_URI,
      ]);
    }
    return $term;
  }

  /**
   * Finds the system property term without creating it.
   *
   * By URI first, then by name among root-level terms. Not by name alone:
   * a collection could have a column called sameInnovationAs, and its term
   * would sit under that collection.
   */
  public function findProperty(): ?TermInterface {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    if ($this->database->schema()->tableExists('rdf_sync_uri')) {
      // rdf_sync keeps the URI in its own table; the URI field is computed
      // and cannot be used in an entity query.
      $tid = $this->database->select('rdf_sync_uri', 'u')
        ->fields('u', ['entity_id'])
        ->condition('u.entity_type', 'taxonomy_term')
        ->condition('u.uri', self::PROPERTY_URI)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      $term = $tid ? $storage->load((int) $tid) : NULL;
      if ($term instanceof TermInterface && $term->bundle() === ContentModel::CLAIM_PROPERTIES) {
        return $term;
      }
    }

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', ContentModel::CLAIM_PROPERTIES)
      ->condition('name', self::PROPERTY_NAME)
      ->condition('parent', 0)
      ->sort('tid')
      ->range(0, 1)
      ->execute();
    $term = $ids ? $storage->load((int) reset($ids)) : NULL;
    return $term instanceof TermInterface ? $term : NULL;
  }

  /**
   * Adds the object to related_solutions, if the field exists.
   *
   * A projection of the claim for Joinup's displays, not the record of it.
   * The caller saves.
   */
  private function mirror(NodeInterface $subject, int $objectId): void {
    if (!$subject->hasField(ContentModel::RELATED_SOLUTIONS)) {
      return;
    }
    foreach ($subject->get(ContentModel::RELATED_SOLUTIONS) as $item) {
      if ((int) $item->target_id === $objectId) {
        return;
      }
    }
    $subject->get(ContentModel::RELATED_SOLUTIONS)->appendItem(['target_id' => $objectId]);
  }

  /**
   * Fails loudly when a claim cannot be written on the solution.
   */
  private function assertWritable(NodeInterface $solution): void {
    if (!$solution->hasField(ContentModel::CLAIMS)) {
      throw new \LogicException(sprintf('Solution %d has no %s field.', $solution->id(), ContentModel::CLAIMS));
    }
    $claimFields = $this->entityFieldManager->getFieldDefinitions('paragraph', ContentModel::CLAIM);
    foreach ([ContentModel::CLAIMED_BY, ContentModel::CLAIM_PROPERTY, ContentModel::CLAIM_REF_NODE] as $field) {
      if (!isset($claimFields[$field])) {
        throw new \LogicException(sprintf('The %s paragraph has no %s field; run config import.', ContentModel::CLAIM, $field));
      }
    }
    if (!$solution->hasField(ContentModel::AUDIENCE) || $solution->get(ContentModel::AUDIENCE)->isEmpty()) {
      throw new \LogicException(sprintf('Solution %d has no collection to attribute its sameInnovationAs claim to.', $solution->id()));
    }
  }

  /**
   * Reads the URI rdf_sync stored for a term, if rdf_sync tracks it.
   */
  private function storedUri(int $tid): ?string {
    if (!$this->database->schema()->tableExists('rdf_sync_uri')) {
      return NULL;
    }
    $uri = $this->database->select('rdf_sync_uri', 'u')
      ->fields('u', ['uri'])
      ->condition('u.entity_type', 'taxonomy_term')
      ->condition('u.entity_id', $tid)
      ->execute()
      ->fetchField();
    return $uri === FALSE ? NULL : (string) $uri;
  }

}
