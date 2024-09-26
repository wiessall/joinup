<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Behat step definitions to test ADMS-AP validation.
 */
class RdfContext extends RawDrupalContext {

  /**
   * Cleans the graph from the ADMS data.
   *
   * @param string $graph
   *   The graph to be cleaned.
   *
   * @Given there are no ADMS data in the :graph graph
   */
  public function cleanGraphFromAdmsData(string $graph): void {
    $admsClasses = [
      'http://www.w3.org/ns/dcat#Catalogue',
      'http://www.w3.org/ns/dcat#Dataset',
      'http://www.w3.org/ns/dcat#Distribution',
      'http://purl.org/dc/terms/LicenseDocument',
      'http://www.w3.org/2006/vcard/ns#Kind',
      'http://xmlns.com/foaf/0.1/Agent',
      'http://data.europa.eu/eif/adms/PerspectiveEIF',
    ];

    $serializedClasses = implode(' ', array_map(function ($class) {
      return "<$class>";
    }, $admsClasses));

    $query = <<<SPARQL
      WITH <$graph>
      DELETE {
        ?s ?p ?o .
      }
      WHERE {
        ?s ?p ?o .
        ?s a ?class .
        VALUES ?class { $serializedClasses }
      }
    SPARQL;

    /** @var \Drupal\rdf_sync\RdfSyncConnectionInterface $connection */
    $connection = \Drupal::service('rdf_sync.connection');
    $connection->query($query);
  }

  /**
   * Asserts that a list of triples exists in a certain graph.
   *
   * It also asserts that no other triples for a given subject
   * exist.
   *
   * Each row is a triple serialized as NTriples:
   * | subject         | predicate         | object            |
   * | http://subject1 | http://predicate1 | http://reference1 |
   * | http://subject1 | http://predicate2 | value1            |
   * | http://subject2 | http://predicate3 | value2            |
   *
   * @param string $graph
   *   The graph to be queried.
   * @param \Behat\Gherkin\Node\TableNode $triples
   *   The user data.
   *
   * @Then the following :graph graph triples exist in the RDF backend:
   */
  public function assertTriples(string $graph, TableNode $triples): void {
    /** @var \Drupal\rdf_sync\RdfSyncConnectionInterface $connection */
    $connection = \Drupal::service('rdf_sync.connection');

    $subjects = $rows = [];
    foreach ($triples->getHash() as $triple) {
      $subjects[$triple['subject']][] = $rows[implode(' ', $triple)] = $triple;
    }

    $unexpectedTriples = [];
    foreach (array_keys($subjects) as $subject) {
      $query = "SELECT ?p ?o WHERE { GRAPH <$graph> { <$subject> ?p ?o }}";
      $results = $connection->query($query);
      /** @var \EasyRdf\Sparql\Result $result */
      foreach ($results as $result) {
        $triple = [
          'subject' => $subject,
          'predicate' => (string) $result->p,
          'object' => trim((string) $result->o),
        ];
        if (in_array($triple, $rows)) {
          unset($rows[implode(' ', $triple)]);
        }
        else {
          $unexpectedTriples[implode(' ', $triple)] = $triple;
        }
      }
    }

    if (!empty($rows)) {
      throw new ExpectationFailedException(sprintf("Triple(s) %s doesn't exists in the RDF backend", implode(PHP_EOL, array_keys($rows))));
    }
    if (!empty($unexpectedTriples)) {
      throw new ExpectationFailedException(sprintf("These triple(s) %s were unexpectedly found in the RDF backend", implode(PHP_EOL, array_keys($unexpectedTriples))));
    }

  }

  /**
   * Asserts that entities in the published graph are ADMS-AP compliant.
   *
   * @Then the ADMS-AP data of the published entities in Joinup is valid
   */
  public function assertValidPublishedGraph(): void {
    $config = \Drupal::config('rdf_sync.settings');
    $graphUri = $config->get('graph_uri');
    /** @var \Drupal\rdf_sync\RdfSyncConnectionInterface $sparqlEndpoint */
    $sparqlEndpoint = \Drupal::service('rdf_sync.connection');
    // Copy the data to a new graph to avoid deleting the original data.
    $sparqlEndpoint->query("ADD <$graphUri> TO GRAPH <http://adms-validation>");
    /** @var \Drupal\adms_validator\AdmsValidatorInterface $validator */
    $validator = \Drupal::service('adms_validator.validator');
    $result = $validator->validateByGraphUri('http://adms-validation');
    $sparqlEndpoint->query('CLEAR GRAPH <http://adms-validation>');
    Assert::assertTrue($result->isSuccessful(), 'The published graph is not ADMS-AP compliant.');
  }

}
