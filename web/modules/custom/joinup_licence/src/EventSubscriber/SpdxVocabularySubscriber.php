<?php

declare(strict_types=1);

namespace Drupal\joinup_licence\EventSubscriber;

use Drupal\joinup_licence\Entity\SpdxLicenceInterface;
use Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to graph preparation event.
 */
class SpdxVocabularySubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PrepareVocabularyGraphEvent::class => 'identifyAsTerm',
      // Act after RdfSyncNormalizeSubscriber::addTermConcept() in order to be
      // able to override the 'rdf:type' value.
      // @see \Drupal\rdf_sync\EventSubscriber\RdfSyncNormalizeSubscriber::addTermConcept()
      RdfSyncNormalizeEvent::class => ['setLicenceAsType', -10],
    ];
  }

  /**
   * Makes licences identify as terms.
   *
   * @param \Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent $event
   *   The event.
   */
  public function identifyAsTerm(PrepareVocabularyGraphEvent $event): void {
    if ($event->vid === 'spdx_licence') {
      foreach ($event->getGraph()->resources() as $resource) {
        if ($resource->isA('http://spdx.org/rdf/terms#ListedLicense')) {
          // Add a skos:inScheme triple to recognize this resource as a term.
          $event->getGraph()->addResource($resource, 'skos:inScheme', 'http://spdx.org/rdf/terms#License');
          continue;
        }
        $event->getGraph()->deleteEntireResource($resource);
      }
    }
  }

  /**
   * Alters the SPDX Licence triples during normalization.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event.
   */
  public function setLicenceAsType(RdfSyncNormalizeEvent $event): void {
    if ($event->getEntity() instanceof SpdxLicenceInterface) {
      $normalizedArray = $event->getNormalizedArray();
      $subject = key($normalizedArray);
      // Restore the type from skos:Concept to spdx:ListedLicense.
      $normalizedArray[$subject]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] = [
        (new Resource('http://spdx.org/rdf/terms#ListedLicense'))->toRdfPhp(),
      ];
      $event->setNormalizedArray($normalizedArray);
    }
  }

}
