<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\Event\PermissionEventInterface;
use Drupal\og\GroupPermission;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Lets collection roles be granted the import review of their own collection.
 */
final class OgPermissionSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public static function getSubscribedEvents(): array {
    return [PermissionEventInterface::EVENT_NAME => [['providePermissions']]];
  }

  public function providePermissions(PermissionEventInterface $event): void {
    $event->setPermissions([
      new GroupPermission([
        'name' => 'review sti duplicates',
        'title' => $this->t('Review the import duplicates of this collection'),
        'restrict access' => TRUE,
      ]),
    ]);
  }

}
