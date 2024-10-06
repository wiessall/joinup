<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;

/**
 * Behat step definitions to test ADMS-AP validation.
 */
class RdfContext extends RawDrupalContext {

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
    // Run some queries in order to get the actual bnode IDs from the database
    // and manage to assert the triples properly.
    $tableRows = $this->parseEifData($triples->getHash());
    foreach ($tableRows as $triple) {
      // Allow to skip the changed date because when performing CRUD operations
      // on an entity, other entities are affected. For example, updating a
      // solution, the collection is updated so that the last update date is
      // affected.
      if ($triple['object'] === '__changed__') {
        $entity = \Drupal::getContainer()->get('rdf_sync.mapper')->getEntityByUri($triple['subject']);
        $date = date(\DateTimeInterface::ATOM, $entity->getChangedTime());
        // Copied from \EasyRdf\Literal\DateTime::__construct().
        $date = preg_replace('/[+\-]00(:?)00$/', 'Z', $date);
        $triple['object'] = $date;
      }
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
   * Asserts the triples with blank nodes in subject or object.
   *
   * @param array $rows
   *   An array of data. Each row has the keys 'subject', 'predicate', 'object'.
   *
   * @return array
   *   The rows to check the triples with the blank nodes replaced by their
   *   actual blank node values in the database.
   */
  protected function parseEifData(array $rows): array {
    $triplesWithBnodesAsSubject = $triplesWithBnodesAsObject = $subjects = $objects = [];
    foreach ($rows as $index => $row) {
      if (str_starts_with($row['subject'], 'bnode:')) {
        $triplesWithBnodesAsSubject[$row['subject']][] = $row['predicate'] . ' ' . $row['object'];
        // The subject here is the bnode.
        $subjects[$row['subject']] = $row['subject'];
      }
      elseif (str_starts_with($row['object'], 'bnode:')) {
        $triplesWithBnodesAsObject[$row['object']][] = $row['predicate'] . ' ' . $row['object'];
        if (isset($objects[$row['object']]) && $objects[$row['object']] !== $row['predicate']) {
          throw new ExpectationFailedException('Currently, only one predicate per object can be tested.');
        }
        // The subject here is the entity referencing the bnode.
        $objects[$row['subject']][$row['object']] = [
          // The predicate of the bnode.
          'predicate' => $row['predicate'],
          // The bnode.
          'object' => $row['object'],
        ];
      }

      unset($rows[$index]);
    }

    if (empty($subjects) && empty($objects)) {
      return $rows;
    }

    $objectsSum = array_sum(array_map('count', $objects));
    // Mainly assert that for each bnode, we have also triples to assert.
    assertSame(count($subjects), $objectsSum, 'The number of blank node identifiers as subject must match the number of blank node identifiers as object');
    $connection = \Drupal::getContainer()->get('rdf_sync.connection');

    // Start from the objects. Get all data from the database for the given
    // subject and the given predicate, but also get the blank node data.
    $queryTemplate = <<<SPARQL
SELECT ?s ?o ?bnpredicate ?bnobject WHERE {
  GRAPH ?g {
    ?s ?p ?o .
    ?o ?bnpredicate ?bnobject .
    FILTER (?s IN (<%s>))
    FILTER (?p IN (<%s>))
  }
}
SPARQL;

    $findSubject = function (array $subjects, string $object): ?string {
      foreach ($subjects as $subject => $objects) {
        foreach ($objects as $objectData) {
          if ($objectData['object'] === $object) {
            return $subject;
          }
        }
      }
      return NULL;
    };

    $bnodesFound = [];
    foreach ($triplesWithBnodesAsObject as $bnode => $triples) {
      if (in_array($bnode, $bnodesFound)) {
        continue;
      }
      $subject = $findSubject($objects, $bnode);
      if (!$subject) {
        throw new ExpectationFailedException('The subject for the bnode ' . $bnode . ' was not found.');
      }

      $predicate = $objects[$subject][$bnode]['predicate'];
      $query = sprintf($queryTemplate, $subject, $predicate);
      $results = $connection->query($query);
      $bnodedResults = [];
      // Setup results by bnode.
      foreach ($results as $result) {
        $bnodeSubject = (string) $result->o;
        $bnpredicate = (string) $result->bnpredicate;
        $bnobject = (string) $result->bnobject;
        $bnodedResults[$bnodeSubject][] = $bnpredicate . ' ' . $bnobject;
      }

      foreach ($triplesWithBnodesAsSubject as $bnodeIndex => $serializedExpected) {
        sort($serializedExpected);
        foreach ($bnodedResults as $resultsIndex => $serializedActual) {
          sort($serializedActual);
          if ($serializedActual === $serializedExpected) {
            unset($triplesWithBnodesAsSubject[$bnodeIndex]);
            unset($bnodedResults[$resultsIndex]);
            $bnodesFound[] = $bnodeIndex;
          }
        }
      }

      if (!empty($bnodedResults)) {
        throw new ExpectationFailedException('There are still triples in the database for bnode ' . $bnode . ' that do not match the expected triples.');
      }
    }

    // Assert that all bnodes were found and the values were matched.
    assertCount(0, $triplesWithBnodesAsSubject, 'The triples with blank nodes as subject were not found');

    return $rows;
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
