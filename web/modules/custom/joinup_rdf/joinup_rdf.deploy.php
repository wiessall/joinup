<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\rdf_sync\Model\SyncMethod;

/**
 * Migrate contact information entities to nodes.
 */
function joinup_rdf_deploy_110700(array &$sandbox): string {
  $endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  if (empty($sandbox['ids'])) {
    $results = $endpoint->query('SELECT ?id FROM <http://joinup.eu/contact-information/published> WHERE { ?id a ?type }')->getArrayCopy();
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
function joinup_rdf_deploy_110701(): string {
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
