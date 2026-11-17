<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\EventSubscriber;

use Drupal\import_ird_jsons\Resolver\ResolutionStore;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A rollback forgets which entities entity_fuzzy_lookup generated during that migration, deleted ones too.
 *
 * Without it, a stub deleted after a test import would never be generated again by a fresh import.
 */
final class RollbackSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ResolutionStore $store,
    private readonly LoggerInterface $logger,
  ) {}

  public static function getSubscribedEvents(): array {
    return [MigrateEvents::POST_ROLLBACK => 'forget'];
  }

  public function forget(MigrateRollbackEvent $event): void {
    $id = (string) $event->getMigration()->id();
    if ($count = $this->store->forgetGenerated($id)) {
      $this->logger->info('Rollback of @id: forgot @count generated entities, so a new import resolves those names afresh.',
        ['@id' => $id, '@count' => $count]);
    }
  }

}
