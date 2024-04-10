<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy\EventSubscriber;

use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to normalization of entities for RDF format encoding.
 */
class NormalizeSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => [
        ['addLocationTypeToCountry'],
      ],
    ];
  }

  /**
   * Adds the dc:Location type to countries.
   *
   * Languages are required to be of type http://purl.org/dc/terms/Location but
   * are listed as http://www.w3.org/2004/02/skos/core#Concept which is also
   * correct. Add the type <http://purl.org/dc/terms/Location> for each language
   * for the 'spatial' vocabulary terms.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   *
   * @see https://webgate.ec.europa.eu/CITnet/jira/browse/ISAICP-4566
   */
  public function addLocationTypeToCountry(RdfSyncNormalizeEvent $event): void {
    if ($event->getEntity()->bundle() === 'spatial') {
      $normalizedArray = $event->getNormalizedArray();
      $subject = key($normalizedArray);
      $normalizedArray[$subject]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][] = (new Resource('http://purl.org/dc/terms/Location'))->toRdfPhp();
      $event->setNormalizedArray($normalizedArray);
    }
  }

}
