<?php

declare(strict_types=1);

namespace Drupal\eira\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\taxonomy\TermInterface;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts EIRA extra-properties to triples.
 */
class NormalizeSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => 'normalizeEiraExtraProperties',
    ];
  }

  /**
   * Coverts extra-properties from JSON to triples.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   */
  public function normalizeEiraExtraProperties(RdfSyncNormalizeEvent $event): void {
    $term = $event->getEntity();
    if (!$term instanceof TermInterface || $term->bundle() !== 'eira') {
      return;
    }

    if (!$data = Json::decode($term->get('extra_properties')->value ?? '') ?? []) {
      return;
    }

    RdfNamespace::set('eira', 'http://data.europa.eu/dr8/');
    // Replace the `dc` namespace with `dct` - requested by the client.
    RdfNamespace::delete('dc');
    RdfNamespace::delete('dcterms');
    RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
    RdfNamespace::set('owlturtle', 'http://www.semanticweb.org/owl/owlapi/turtle#');

    $normalizedArray = $event->getNormalizedArray();
    $subject = key($normalizedArray);

    if (isset($normalizedArray[$subject]['http://www.w3.org/2002/07/owl#deprecated'])) {
      // EIRA 6.0.0 is wrongly setting the type of `owl:deprecated` predicate as
      // string. We're casting to a boolean when importing from file. Revert to
      // original literal, in order to comply with EIRA original RDF.
      // @see \Drupal\joinup_taxonomy\EventSubscriber\PrepareVocabularyGraphSubscriber::alterEira()
      unset($normalizedArray[$subject]['http://www.w3.org/2002/07/owl#deprecated'][0]['datatype']);
    }

    $graph = new Graph();
    $graph->parse($normalizedArray, 'php');
    // The subject is suffixed with '_Properties'.
    $propertiesSubject = "{$subject}_Properties";

    foreach ($data['rows'] ?? [] as $row) {
      $graph->add($propertiesSubject, RdfNamespace::expand($row['property']), $row['value']);
    }

    // Add back skos:topConceptOf and skos:related resources. These were removed
    // on import from the EIRA_SKOS.rdf file.
    $graph->addResource($propertiesSubject, 'http://www.w3.org/2004/02/skos/core#topConceptOf', 'http://data.europa.eu/dr8');
    $graph->addResource($propertiesSubject, 'http://www.w3.org/2004/02/skos/core#related', $subject);

    // Remove the JSON encoded triple.
    $graph->delete($subject, new Resource('http://joinup.eu/eira/properties'));

    $event->setNormalizedArray($graph->toRdfPhp());
  }

}
