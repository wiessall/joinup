<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_rdf\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\sti_rdf\Service\MappingCuration;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests proposing, accepting and rejecting mappings.
 *
 * The silent failures: a decision saved as a new paragraph revision leaves
 * the collection pointing at the undecided copy; and a rejected mapping that
 * can be proposed again undoes the reviewer's decision.
 */
#[CoversClass(MappingCuration::class)]
#[Group('sti_rdf')]
#[RunTestsInSeparateProcesses]
final class MappingCurationTest extends KernelTestBase {

  use UserCreationTrait;

  private const CLOSE = 'http://www.w3.org/2004/02/skos/core#closeMatch';

  private const EXACT = 'http://www.w3.org/2004/02/skos/core#exactMatch';

  private const READINESS = 'https://sti-portal.fao.org/atio#readinessLevel';

  private const STAGE = 'https://sti-portal.fao.org/atio#innovationStage';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'text',
    'filter',
    'options',
    'node',
    'taxonomy',
    'entity_reference_revisions',
    'paragraphs',
    'serialization',
    'rdf_sync',
    'sti_rdf',
  ];

  /**
   * The issuing collection.
   */
  private NodeInterface $collection;

  /**
   * A property of the collection, the subject of every mapping.
   */
  private TermInterface $country;

  /**
   * A target property.
   */
  private TermInterface $nation;

  /**
   * Another target property.
   */
  private TermInterface $region;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('rdf_sync.synchronizer', RecordingSynchronizer::class)->setPublic(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'node', 'taxonomy_term', 'paragraph'] as $type) {
      $this->installEntitySchema($type);
    }
    // Saving a node writes its access records.
    $this->installSchema('node', ['node_access']);
    $this->installSchema('rdf_sync', ['rdf_sync_uri']);
    $this->installConfig(['field', 'filter', 'node']);
    NodeType::create(['type' => 'collection', 'name' => 'Collection'])->save();
    // After the node type, so the optional field_mappings is installed too.
    $this->installConfig(['sti_rdf']);

    // Through the entity API, so rdf_sync's mapper sees the mapping.
    Vocabulary::create([
      'vid' => 'claim_properties',
      'name' => 'Claim properties',
      'third_party_settings' => [
        'rdf_sync' => [
          'type' => 'http://www.w3.org/2004/02/skos/core#Concept',
          'uri_plugin' => 'sti_namespace:property',
          'fields' => [],
        ],
      ],
    ])->save();
    Vocabulary::create(['vid' => 'tags', 'name' => 'Not in the graph'])->save();

    $this->country = $this->term('claim_properties', 'country');
    $this->nation = $this->term('claim_properties', 'nation');
    $this->region = $this->term('claim_properties', 'region');
    $this->collection = Node::create(['type' => 'collection', 'title' => 'TECA']);
    $this->collection->save();
    $this->setUpCurrentUser([], [], TRUE);
  }

  /**
   * Proposals are unpublished, and nothing is proposed twice.
   */
  public function testProposalsAreIdempotentAndRemembered(): void {
    $rows = [
      ['subject' => $this->country, 'relation' => self::CLOSE, 'object' => $this->nation],
      ['subject' => $this->country, 'relation' => self::EXACT, 'object' => $this->region],
    ];
    $this->assertSame(2, $this->curation()->propose($this->collection, $rows));
    $mappings = $this->mappings();
    $this->assertCount(2, $mappings);
    $this->assertFalse($mappings[0]->isPublished(), 'A proposal stays out of the graph.');

    $this->assertSame(0, $this->curation()->propose($this->reload(), $rows), 'Proposing again adds nothing.');

    $this->curation()->reject($mappings[0]);
    $this->assertSame(0, $this->curation()->propose($this->reload(), [$rows[0]]), 'A rejected mapping is not proposed again.');
    $this->assertCount(2, $this->mappings());
  }

  /**
   * Decisions land on the revision the collection references.
   */
  public function testDecisionsKeepTheCollectionsRevision(): void {
    $this->curation()->propose($this->collection, [
      ['subject' => $this->country, 'relation' => self::CLOSE, 'object' => $this->nation],
      ['subject' => $this->country, 'relation' => self::EXACT, 'object' => $this->region],
    ]);
    [$accepted, $rejected] = $this->mappings();
    $this->curation()->accept($accepted);
    $this->curation()->reject($rejected);

    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $storage->resetCache();
    $referenced = [];
    foreach ($this->reload()->get(MappingCuration::FIELD) as $item) {
      $referenced[] = $storage->loadRevision($item->target_revision_id);
    }
    $this->assertCount(2, $referenced);
    foreach ($referenced as $mapping) {
      $this->assertTrue($mapping->isPublished(), 'The collection shows the decided revision.');
      $this->assertSame((int) $this->container->get('current_user')->id(), (int) $mapping->get('field_mapping_reviewer')->target_id);
    }
    $this->assertTrue($referenced[0]->get('field_mapping_modifier')->isEmpty());
    $this->assertSame(MappingCuration::NEGATED, $referenced[1]->get('field_mapping_modifier')->value);
  }

  /**
   * A term outside the graph is refused, and nothing is saved.
   */
  public function testTermOutsideTheGraphIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    try {
      $this->curation()->propose($this->collection, [
        ['subject' => $this->country, 'relation' => self::CLOSE, 'object' => $this->nation],
        ['subject' => $this->country, 'relation' => self::CLOSE, 'object' => $this->term('tags', 'land')],
      ]);
    }
    finally {
      $this->assertSame([], $this->mappings());
    }
  }

  /**
   * ATIO properties are offered live from rdf_sync, and orphans are found.
   */
  public function testAtioPredicateTargetsAndOrphans(): void {
    $this->mapAtio([
      'atio_readiness_level' => self::READINESS,
      'atio_innovation_stage' => self::STAGE,
      'title' => 'http://purl.org/dc/terms/title',
    ]);
    $this->assertEqualsCanonicalizing([self::READINESS, self::STAGE], array_keys($this->curation()->atioPredicates()), 'Only atio: predicates are targets.');

    $this->assertSame(2, $this->curation()->propose($this->collection, [
      ['subject' => $this->country, 'relation' => self::CLOSE, 'predicate' => self::READINESS],
      ['subject' => $this->nation, 'relation' => self::EXACT, 'predicate' => self::STAGE],
    ]));
    $this->assertSame(0, $this->curation()->propose($this->reload(), [
      ['subject' => $this->country, 'relation' => self::CLOSE, 'predicate' => self::READINESS],
    ]), 'A predicate mapping is not proposed twice.');
    $this->assertSame([], $this->curation()->orphans());

    // The stage field loses its predicate: its mapping must be reported.
    $this->mapAtio(['atio_readiness_level' => self::READINESS]);
    $orphans = $this->curation()->orphans();
    $this->assertCount(1, $orphans);
    $this->assertSame(self::STAGE, $orphans[0]->get('field_mapping_target_predicate')->value);
  }

  /**
   * A target must be a term or a known ATIO property, exactly one of them.
   */
  public function testInvalidPredicateTargetsAreRefused(): void {
    $this->mapAtio(['atio_readiness_level' => self::READINESS]);
    foreach ([
      ['subject' => $this->country, 'relation' => self::CLOSE, 'predicate' => self::STAGE],
      ['subject' => $this->country, 'relation' => self::CLOSE, 'predicate' => self::READINESS, 'object' => $this->nation],
    ] as $row) {
      try {
        $this->curation()->propose($this->collection, [$row]);
        $this->fail('An invalid target was accepted.');
      }
      catch (\InvalidArgumentException) {
        // Expected.
      }
    }
    $this->assertSame([], $this->mappings());
  }

  /**
   * Maps atio_innovation fields to predicates, as rdf_sync config does.
   *
   * @param array<string, string> $predicates
   *   Predicate URI keyed by field name.
   */
  private function mapAtio(array $predicates): void {
    $type = NodeType::load('atio_innovation') ?? NodeType::create(['type' => 'atio_innovation', 'name' => 'ATIO innovation']);
    $fields = [];
    foreach ($predicates as $field => $predicate) {
      $fields[$field] = ['target_id' => ['predicate' => $predicate, 'type' => 'resource']];
    }
    $type->setThirdPartySetting('rdf_sync', 'type', 'https://sti-portal.fao.org/atio#Innovation');
    $type->setThirdPartySetting('rdf_sync', 'uri_plugin', 'sti_namespace:innovation');
    $type->setThirdPartySetting('rdf_sync', 'fields', $fields);
    $type->save();
  }

  /**
   * Returns the service under test.
   */
  private function curation(): MappingCuration {
    return $this->container->get('sti_rdf.mapping_curation');
  }

  /**
   * Returns the collection's mappings, freshly loaded.
   *
   * @return list<\Drupal\paragraphs\ParagraphInterface>
   *   The mappings.
   */
  private function mappings(): array {
    $this->container->get('entity_type.manager')->getStorage('paragraph')->resetCache();
    return $this->curation()->mappings($this->reload());
  }

  /**
   * Reloads the collection.
   */
  private function reload(): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$this->collection->id()]);
    return $storage->load($this->collection->id());
  }

  /**
   * Creates a term.
   */
  private function term(string $vid, string $name): TermInterface {
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();
    return $term;
  }

}
