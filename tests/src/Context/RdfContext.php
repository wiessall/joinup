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
    $connection = \Drupal::getContainer()->get('rdf_sync.connection');
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

  /**
   * Updates an entity, given its RDF resource (URI).
   *
   * @codingStandardsIgnoreStart
   *  Table format:
   *  | title               | Sample solution   |
   *  | description         | A sample solution |
   *  | ...                 | ...               |
   * @codingStandardsIgnoreEnd
   *
   * @param string $uri
   *   The entity RDF URI.
   * @param \Behat\Gherkin\Node\TableNode $fieldsTable
   *   The values of the fields to update.
   *
   * @throws \Exception
   *    Thrown when a field name is invalid.
   * @throws \InvalidArgumentException
   *   When for the passed URI no entity can be found.
   *
   * @When I update the entity having :uri as RDF resource:
   */
  public function updateEntityByRdfUri(string $uri, TableNode $fieldsTable): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface|null $entity */
    if (!$entity = \Drupal::getContainer()->get('rdf_sync.mapper')->getEntityByUri($uri)) {
      throw new \InvalidArgumentException("No entity found for URI $uri");
    }
    $updated = (object) $fieldsTable->getRowsHash();
    $updated->type = $entity->bundle();
    $this->parseEntityFields($entity->getEntityTypeId(), $updated);
    foreach (array_keys($fieldsTable->getRowsHash()) as $key) {
      if ($key === 'changed' && !is_numeric($updated->$key)) {
        $updated->$key = strtotime($updated->$key);
      }
      if ($entity->getFieldDefinition($key)->getType() === 'entity_reference') {
        $targetEntityType = $entity->getFieldDefinition($key)->getSetting('target_type');
        foreach ($updated->$key as $delta => $value) {
          $targetEntity = \Drupal::entityTypeManager()->getStorage($targetEntityType)->loadByProperties(['name' => $value]);
          \assert($targetEntity, "No entity found for $key = $value");
          $targetEntity = reset($targetEntity);
          $updated->$key[$delta] = ['target_id' => $targetEntity->id()];
        }

      }
      $entity->set($key, $updated->$key);
    }
    $entity->save();
  }

  /**
   * Visits an entity canonical page given its RDF resource (URI).
   *
   * @param string $uri
   *   The entity RDF URI.
   *
   * @throws \InvalidArgumentException
   *   When for the passed URI no entity can be found.
   *
   * @Given I visit the page of the entity having :uri as RDF resource
   */
  public function visitEntityCanonicalPagePageByRdfUri(string $uri): void {
    if (!$entity = \Drupal::getContainer()->get('rdf_sync.mapper')->getEntityByUri($uri)) {
      throw new \InvalidArgumentException("No entity found for URI $uri");
    }
    $this->visitPath($entity->toUrl()->toString());
  }

  /**
   * Asserts that no triples exist with the given URI as the given property.
   *
   * @param string $property
   *   The property to check. Either 'subject' or 'object'.
   * @param string $uri
   *   The URI to check.
   * @param string $graph
   *   The graph to check.
   *
   * @Then there should not be any triples with the :property :uri in the :graph graph
   */
  public function assertNoTriplesOfUri(string $property, string $uri, string $graph): void {
    assert(in_array($property, ['subject', 'object']), 'The property must be either "subject" or "object"');
    $stub = $property === 'subject' ?
      "<$uri> ?p ?entity" :
      "?entity ?p <$uri>";

    $query = "WITH <$graph> SELECT * WHERE { $stub }";

    /** @var \Drupal\rdf_sync\RdfSyncConnectionInterface $connection */
    $connection = \Drupal::service('rdf_sync.connection');
    $results = $connection->query($query);
    $messages = [];
    foreach ($results as $result) {
      $messages[] = sprintf('%s %s', $result->entity, $result->p);
    }

    Assert::assertCount(0, $results, "There are triples with $property $uri in the $graph graph. Results found: " . implode(', ', $messages));
  }

}
