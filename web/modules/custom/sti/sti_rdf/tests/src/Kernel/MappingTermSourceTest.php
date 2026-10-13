<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_rdf\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\sti_rdf\Service\MappingTermSource;
use Drupal\sti_rdf\StiUri;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the term lists the SKOS mapper aligns.
 *
 * The silent failure: a mapping written against a URI the claims do not use
 * joins nothing in the graph, and nobody notices until a query comes back
 * empty. So the URI must be the stored one, and a term without one must be
 * reported rather than dropped or given a guessed URI.
 */
#[CoversClass(MappingTermSource::class)]
#[Group('sti_rdf')]
final class MappingTermSourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'taxonomy',
    'serialization',
    'rdf_sync',
    'sti_rdf',
  ];

  /**
   * The collection-level term in claim_properties.
   */
  private TermInterface $collection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('rdf_sync', ['rdf_sync_uri']);

    Vocabulary::create(['vid' => 'claim_properties', 'name' => 'Claim properties'])->save();
    Vocabulary::create(['vid' => 'claim_values', 'name' => 'Claim values'])->save();

    $this->collection = $this->term('claim_properties', 'fao-teca');
    $readiness = $this->term('claim_properties', 'readiness', $this->collection);
    $this->term('claim_properties', 'never synced', $this->collection);
    // A system predicate: root level, no children.
    $this->term('claim_properties', 'sameInnovationAs');

    $this->container->get('database')->insert('rdf_sync_uri')->fields([
      'entity_type' => 'taxonomy_term',
      'entity_id' => $readiness->id(),
      'bundle' => 'claim_properties',
      'uri' => StiUri::forEntity('property', $readiness->uuid()),
    ])->execute();

    NodeType::create(['type' => 'atio_innovation', 'name' => 'ATIO innovation'])->save();
    $config = $this->config('node.type.atio_innovation');
    $config->set('third_party_settings.rdf_sync', [
      'type' => StiUri::ATIO . 'Innovation',
      'uri_plugin' => 'sti_namespace:innovation',
      'fields' => [
        'title' => ['value' => ['predicate' => 'http://purl.org/dc/terms/title', 'type' => NULL]],
        'atio_readiness_level' => [
          'target_id' => ['predicate' => StiUri::ATIO . 'readinessLevel', 'type' => 'resource'],
        ],
        'atio_start_year' => ['value' => ['predicate' => StiUri::ATIO . 'startYear', 'type' => NULL]],
      ],
    ]);
    $config->set('dependencies.module', ['rdf_sync']);
    $config->save();
    $this->container->get('entity_type.manager')->getStorage('node_type')->resetCache(['atio_innovation']);
  }

  /**
   * ATIO first, then collections; system predicates are not a source.
   */
  public function testSources(): void {
    $this->assertSame([
      ['id' => 'atio', 'label' => 'ATIO innovation fields'],
      ['id' => (string) $this->collection->id(), 'label' => 'fao-teca'],
    ], $this->source()->sources());
  }

  /**
   * Collection terms carry the stored URI; unsynced ones are reported.
   */
  public function testCollectionTermsUseStoredUris(): void {
    $result = $this->source()->terms((string) $this->collection->id());
    $readiness = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties(['name' => 'readiness']);
    $readiness = reset($readiness);

    $this->assertSame('fao-teca', $result['meta']['label']);
    $this->assertSame([['label' => 'readiness', 'uri' => StiUri::forEntity('property', $readiness->uuid())]], $result['terms']);
    $this->assertSame(['never synced'], $result['unsynced']);
  }

  /**
   * ATIO terms are the atio: predicates of the innovation bundle only.
   */
  public function testAtioTermsAreAtioPredicates(): void {
    $uris = array_column($this->source()->terms('atio')['terms'], 'uri');
    $this->assertEqualsCanonicalizing([StiUri::ATIO . 'readinessLevel', StiUri::ATIO . 'startYear'], $uris);
  }

  /**
   * Anything that is not a claim_properties term is refused.
   */
  public function testUnknownSourceIsRefused(): void {
    $value = $this->term('claim_values', 'TRL 5');
    foreach (['999999', (string) $value->id()] as $source) {
      try {
        $this->source()->terms($source);
        $this->fail("Source $source should be refused.");
      }
      catch (\InvalidArgumentException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  /**
   * Creates a term.
   */
  private function term(string $vid, string $name, ?TermInterface $parent = NULL): TermInterface {
    $term = Term::create(['vid' => $vid, 'name' => $name, 'parent' => [$parent?->id() ?? 0]]);
    $term->save();
    return $term;
  }

  /**
   * Returns the service under test.
   */
  private function source(): MappingTermSource {
    return $this->container->get('sti_rdf.mapping_term_source');
  }

}
