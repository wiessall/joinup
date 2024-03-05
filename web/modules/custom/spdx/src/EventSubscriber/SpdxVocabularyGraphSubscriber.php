<?php

declare(strict_types=1);

namespace Drupal\spdx\EventSubscriber;

use Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to graph preparation event.
 */
class SpdxVocabularyGraphSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PrepareVocabularyGraphEvent::class => 'identifyAsTerm',
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
          $event->getGraph()->addResource($resource, 'skos:inScheme', 'http://spdx.org/rdf/terms#License');
        }
        else {
          $event->getGraph()->deleteEntireResource($resource);
        }
      }
    }
  }

}
