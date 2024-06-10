<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\Component\Serialization\Json;
use Drupal\Core\Serialization\Yaml;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\node\Entity\Node;
use Drupal\rdf_sync\Model\SyncMethod;

/**
 * Migrate owners to nodes.
 */
function joinup_rdf_deploy_110800(array &$sandbox): string {
  $map = [
    'http://xmlns.com/foaf/0.1/name' => 'title',
    'http://purl.org/dc/terms/type' => 'owner_type',
    'http://joinup.eu/owner/uid' => 'uid',
    'http://joinup.eu/owner/workflow_status' => 'field_state',
  ];
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  if (!isset($sandbox['owners'])) {
    $sandbox['owners'] = Json::decode(file_get_contents('private://owners'));
    \Drupal::getContainer()->get('file_system')->delete('private://owners');
    $sandbox['progress'] = 0;
    $query = <<<SPARQL
      SELECT DISTINCT(?o)
      FROM NAMED <http://joinup.eu/collection/published>
      FROM NAMED <http://joinup.eu/collection/draft>
      FROM NAMED <http://joinup.eu/solution/published>
      FROM NAMED <http://joinup.eu/solution/draft>
      FROM NAMED <http://joinup.eu/asset_release/published>
      FROM NAMED <http://joinup.eu/asset_release/draft>
      WHERE {
        GRAPH ?g {
          ?s <http://purl.org/dc/terms/publisher> ?o .
        }
      }
      SPARQL;
    $results = \Drupal::getContainer()->get('sparql.endpoint')->query($query);
    $sandbox['referred'] = array_map(fn(\stdClass $row): string => $row->o->getUri(), $results->getArrayCopy());
    $sandbox['total'] = count($sandbox['owners']);
  }
  $count = 0;
  while ($sandbox['owners'] && $count < 25) {
    $uri = key($sandbox['owners']);
    $data = current($sandbox['owners']);
    unset($sandbox['owners'][$uri]);
    if (!in_array($uri, $sandbox['referred'], TRUE)) {
      // Don't convert owners which are not referred.
      continue;
    }

    $values = ['type' => 'owner', 'uri' => $uri];
    foreach ($data as $predicate => $objects) {
      if (isset($map[$predicate])) {
        $fieldName = $map[$predicate];
        foreach ($objects as $object) {
          $value = $object['value'];
          if ($fieldName === 'owner_type') {
            $value = MappedTerm::loadByUri($value)->id();
          }
          $values[$fieldName][] = $value;
          if ($fieldName === 'title' && strlen($value) > 255) {
            $values['title'] = substr($value, 0, 252) . '...';
          }
        }
      }
    }
    $storage->create($values)->save();

    $sandbox['progress']++;
    $count++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['owners']);
  return "Imported {$sandbox['progress']} out of {$sandbox['total']} owners";
}

/**
 * Update a duplicate URI.
 */
function joinup_rdf_deploy_110801(): void {
  // The following URI exists as an ID for both an owner and a contact
  // information. We will rename the contact information.
  $uri = 'http://resource.geolba.ac.at/doc/62';
  $new_uri = 'http://resource.geolba.ac.at/doc/62/contact';

  $query = <<<SPARQL
WITH <http://joinup.eu/contact-information/published>
DELETE { <$uri> ?p ?o }
INSERT { <$new_uri> ?p ?o }
WHERE { <$uri> ?p ?o }
SPARQL;

  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $sparql->update($query);

  // Also, update related references from the collection, solution and release
  // graphs.
  $query = <<<SPARQL
DELETE { GRAPH ?g { ?s <http://www.w3.org/ns/dcat#contactPoint> <$uri> } }
INSERT { GRAPH ?g { ?s <http://www.w3.org/ns/dcat#contactPoint> <$new_uri> } }
WHERE {
  GRAPH ?g { ?s <http://www.w3.org/ns/dcat#contactPoint> <$uri> } .
  VALUES ?g {
    <http://joinup.eu/collection/published>
    <http://joinup.eu/collection/draft>
    <http://joinup.eu/solution/published>
    <http://joinup.eu/solution/draft>
    <http://joinup.eu/release/published>
    <http://joinup.eu/release/draft>
  }
}
SPARQL;

  $sparql->update($query);
}

/**
 * Migrate contact information entities to nodes.
 */
function joinup_rdf_deploy_110802(array &$sandbox): string {
  $endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  if (empty($sandbox['ids'])) {
    $results = $endpoint->query('SELECT DISTINCT ?id FROM <http://joinup.eu/contact-information/published> WHERE { ?id a ?type }')->getArrayCopy();
    $sandbox['ids'] = array_map(fn(\stdClass $result) => $result->id->getUri(), $results);
    $sandbox['processed'] = 0;
    $sandbox['total'] = count($sandbox['ids']);
  }

  $mapping = [
    'http://www.w3.org/2006/vcard/ns#fn' => 'title',
    'http://www.w3.org/2006/vcard/ns#hasURL' => 'webpage',
    'http://www.w3.org/2006/vcard/ns#hasEmail' => 'email',
    'http://joinup.eu/owner/uid' => 'uid',
    'http://joinup.eu/language' => 'langcode',
    'http://joinup.eu/contact_information/workflow_status' => 'field_state',
  ];

  $ids = array_splice($sandbox['ids'], 0, 50);
  $nodes = [];
  foreach ($ids as $id) {
    $data = $endpoint
      ->query("SELECT ?property ?value WHERE { <$id> ?property ?value . }")
      ->getArrayCopy();
    $values = _joinup_rdf_deploy_flatten_rdf_values($data);
    foreach ($values as $property => $value) {
      if (isset($mapping[$property])) {
        $values[$mapping[$property]] = $value;
      }
      unset($values[$property]);
    }

    // Clean the entity from the endpoint.
    $endpoint->query("WITH <http://joinup.eu/contact-information/published> DELETE { ?s ?p ?o } WHERE { ?s ?p ?o . VALUES ?s { <$id> } }");

    // Create the node.
    /** @var \Drupal\contact_information\Entity\ContactInformationInterface $node */
    $node = Node::create($values + ['type' => 'contact_information']);
    $node->set('uri', $id);
    $node->save();
    $nodes[] = $node;
  }
  \Drupal::getContainer()->get('rdf_sync.synchronizer')->synchronize(SyncMethod::INSERT, $nodes, TRUE);

  $sandbox['processed'] += count($ids);
  $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];

  return "Migrated {$sandbox['processed']} out of {$sandbox['total']} contact information entities";
}

/**
 * Clear the contact information graph.
 */
function joinup_rdf_deploy_110803(): string {
  // Now that all entities are migrated and synced into the `http://data` graph,
  // we can safely remove the previous one.
  \Drupal::getContainer()
    ->get('sparql.endpoint')
    ->query('CLEAR GRAPH <http://joinup.eu/contact-information/published>');

  return 'Cleared the contact information graph';
}

/**
 * Flatten RDF values.
 *
 * @param \stdClass[] $data
 *   The RDF data.
 *
 * @return array
 *   The flattened values.
 */
function _joinup_rdf_deploy_flatten_rdf_values(array $data): array {
  $values = [];
  foreach ($data as $row) {
    $property = $row->property->getUri();
    $value = method_exists($row->value, 'getUri') ? $row->value->getUri() : $row->value->getValue();
    $values[$property] = $value;
  }
  return $values;
}

/**
 * Restore distributions.
 */
function joinup_rdf_deploy_110804(array &$sandbox): string {
  $map = [
    'http://joinup.eu/ad/workflow_status' => 'field_state',
    'http://joinup.eu/rdf_entity/group' => 'og_audience',
    'http://purl.org/dc/terms/description' => 'description',
    'http://purl.org/dc/terms/format' => 'format',
    'http://purl.org/dc/terms/issued' => 'created',
    'http://purl.org/dc/terms/license' => 'field_licence',
    'http://purl.org/dc/terms/modified' => 'changed',
    'http://purl.org/dc/terms/title' => 'title',
    'http://www.w3.org/ns/adms#representationTechnique' => 'representation_technique',
    'http://www.w3.org/ns/adms#status' => 'process_status',
    'http://www.w3.org/ns/dcat#accessURL' => 'access_url',
    'https://schema.org/fileSize' => 'file_size',
  ];
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  if (!isset($sandbox['files'])) {
    $fileSystem = \Drupal::getContainer()->get('file_system');
    $sandbox['files'] = array_keys($fileSystem->scanDirectory('private://distributions', '/\.yml$/'));
    $sandbox['progress'] = 0;
  }

  $file = array_shift($sandbox['files']);
  $distributions = Yaml::decode(file_get_contents($file));
  foreach ($distributions as $uri => $data) {
    // @todo Implement an RDF sync subscriber to add the distributions to the
    //   parent entity. Add a follow up for the release and solution tickets to
    //   update this subscriber to add the data in the http://data graph
    //   instead.
    $values = ['type' => 'distribution', 'uri' => $uri];
    foreach ($data as $predicate => $objects) {
      if (isset($map[$predicate])) {
        $fieldName = $map[$predicate];
        foreach ($objects as $object) {
          $value = $object['value'];
          // Referential integrity issue: The referred licence is missing. Avoid
          // data inconsistency.
          // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8649
          if ($fieldName === 'field_licence') {
            if (!MappedTerm::loadByUri($value)) {
              continue;
            }
          }
          $value = match ($fieldName) {
            'field_licence', 'format', 'representation_technique' => MappedTerm::loadByUri($value)->id(),
            'description' => ['value' => $value, 'format' => 'content_editor'],
            'changed', 'created' => strtotime($value),
            // Title can't be longer than 255 characters.
            'title' => substr($value, 0, 255),
            default => $value,
          };
          $values[$fieldName][] = $value;
        }
      }
    }
    $values['distribution_parent'] = _joinup_rdf_get_distribution_parent($uri);
    $storage->create($values)->save();
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['files']);
  return "Imported {$sandbox['progress']} distributions";
}

/**
 * Helper function to determine a distribution's parent.
 */
function _joinup_rdf_get_distribution_parent(string $distribution_id): string {
  $query = <<<SPARQL
SELECT ?parent
WHERE {
  GRAPH ?graph {
    ?parent <http://www.w3.org/ns/dcat#distribution> <$distribution_id> .
  }
  VALUES ?graph {
    <http://joinup.eu/solution/published>
    <http://joinup.eu/solution/draft>
    <http://joinup.eu/asset_release/published>
    <http://joinup.eu/asset_release/draft>
  }
}
SPARQL;

  $results = \Drupal::getContainer()->get('sparql.endpoint')->query($query)->getArrayCopy();
  $parent = reset($results);
  if (!$parent) {
    return '';
  }

  return $parent->parent->getUri();
}
