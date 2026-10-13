<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\EventSubscriber;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\ReplacementStore;
use Drupal\sti_rdf\Event\UriResolveEvent;
use Drupal\sti_rdf\StiUri;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps the URI of a replaced ATIO innovation resolving to its survivor.
 *
 * A replaced innovation is unpublished, so without this its instance URI
 * would answer 404 the moment a reviewer folds it into another one, breaking
 * every link a partner or crawler already holds.
 */
final class ReplacedInnovationSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly ReplacementStore $replacements,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [UriResolveEvent::class => 'onResolve'];
  }

  /**
   * Points a replaced innovation's URI at its survivor.
   */
  public function onResolve(UriResolveEvent $event): void {
    $parts = StiUri::parse($event->uri);
    if ($parts === NULL || $parts['type'] !== 'innovation') {
      return;
    }
    $replaced = $this->entityRepository->loadEntityByUuid('node', $parts['uuid']);
    if ($replaced === NULL || $replaced->bundle() !== ContentModel::INNOVATION) {
      return;
    }
    $survivorNid = $this->replacements->survivorOf((int) $replaced->id());
    if ($survivorNid === NULL) {
      return;
    }
    $survivor = $this->entityRepository->getActive('node', $survivorNid);
    if ($survivor !== NULL) {
      $event->setReplacementUri(StiUri::forEntity('innovation', (string) $survivor->uuid()));
    }
  }

}
