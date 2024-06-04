<?php

declare(strict_types=1);

namespace Drupal\Tests\eif\ExistingSite;

use Drupal\Core\Serialization\Yaml;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Tests EIF perspective synchronization with the RDF backend.
 */
class EifRdfSyncTest extends JoinupExistingSiteTestBase {

  /**
   * Tests synchronization of EIF perspective data.
   */
  public function testRdfSync(): void {
    $solution = $this->createRdfEntity([
      'rid' => 'solution',
      'label' => $this->randomString(),
    ]);
    /** @var \Drupal\eif\Entity\EifPerspectiveInterface $eif */
    $eif = MetaEntity::create([
      'type' => 'eif_perspective',
      'target' => $solution,
      'eif_description' => 'EIF perspective description',
      'eif_layer' => [
        MappedTerm::loadByUri('http://data.europa.eu/IL1'),
        MappedTerm::loadByUri('http://data.europa.eu/IL3'),
      ],
      'eif_model' => [
        MappedTerm::loadByUri('http://data.europa.eu/BC7'),
        MappedTerm::loadByUri('http://data.europa.eu/BC4'),
        MappedTerm::loadByUri('http://data.europa.eu/BC1'),
      ],
      'eif_principle' => [
        MappedTerm::loadByUri('http://data.europa.eu/UP1'),
      ],
      'eif_recommendation' => [
        [
          'target_id' => MappedTerm::loadByUri('http://data.europa.eu/Rec2')->id(),
          'value' => 'Qualified recommendation 1',
          'format' => 'basic_html',
        ],
        [
          'target_id' => MappedTerm::loadByUri('http://data.europa.eu/Rec21')->id(),
          'value' => 'Qualified recommendation 2',
          'format' => 'basic_html',
        ],
      ],
    ]);
    $eif->save();
    $this->markEntityForCleanup($eif);

    // Commit changes.
    \Drupal::getContainer()->get('rdf_sync.synchronizer')->destruct();

    $eifUri = $eif->getUri();
    $actual = [];

    // Get related blank nodes in a single query.
    $query = <<<SPARQL
      SELECT ?predicate ?object ?bnode_predicate ?bnode_object
      WHERE {
        {
          GRAPH <http://data> {
            <$eifUri> ?predicate ?object .
            FILTER(!isBlank(?object)) .
          }
        }
        UNION
        {
          GRAPH <http://data> {
            <$eifUri> ?predicate ?object .
          }
          GRAPH <http://data> {
            <$eifUri> ?predicate ?object .
            ?object ?bnode_predicate ?bnode_object .
            FILTER (isBlank(?object))
          }
        }
      }
      SPARQL;
    /** @var \Drupal\rdf_sync\RdfSyncConnectionInterface $connection */
    $connection = \Drupal::service('rdf_sync.connection');
    $results = $connection->query($query);
    foreach ($results as $result) {
      $predicate = $result->predicate->getUri();
      if ($predicate === 'http://data.europa.eu/eif/Recommendation') {
        $actual[$predicate][(string) $result->object][$result->bnode_predicate->getUri()] = (string) $result->bnode_object;
      }
      else {
        $actual[$predicate][] = (string) $result->object;
      }
    }
    // Remove blank node IDs. They were used only to structure data.
    $actual['http://data.europa.eu/eif/Recommendation'] = array_values($actual['http://data.europa.eu/eif/Recommendation']);

    $this->assertEqualsCanonicalizing($this->getExpected($solution->id()), $actual);

    // Delete the solution.
    $solution->delete();
    \Drupal::getContainer()->get('rdf_sync.synchronizer')->destruct();

    // Check that after deletion, triples, including blank nodes, were removed.
    $results = $connection->query($query);
    $this->assertSame(0, $results->numRows());
  }

  /**
   * Returns the expected values.
   *
   * @param string $solutionUri
   *   The solution URI.
   *
   * @return array
   *   Expected triples.
   */
  protected function getExpected(string $solutionUri): array {
    $content = file_get_contents(__DIR__ . '/../../fixture/expectancy.yml');
    $content = str_replace('<SOLUTION URI>', $solutionUri, $content);
    return Yaml::decode($content);
  }

}
