<?php

declare(strict_types=1);

namespace Drupal\rdf_sync\EventSubscriber;

use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\taxonomy\TermInterface;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to normalization of entities for RDF format encoding.
 */
class RdfSyncNormalizeSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The mapper service.
   */
  public function __construct(protected RdfSyncMapper $mapper) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => 'addTermConcept',
    ];
  }

  /**
   * Adds the skos:Concept triple to each taxonomy term.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   */
  public function addTermConcept(RdfSyncNormalizeEvent $event): void {
    $entity = $event->getEntity();
    if ($entity instanceof TermInterface) {
      $normalizedArray = $event->getNormalizedArray();
      $normalizedArray[$entity->getUri()]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][] = (new Resource('http://www.w3.org/2004/02/skos/core#Concept'))->toRdfPhp();
      $event->setNormalizedArray($normalizedArray);
    }
  }

}
