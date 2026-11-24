<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\Event\PermissionEventInterface;
use Drupal\og\GroupPermission;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Declares the group permission to review a collection's duplicates.
 */
final class OgPermissionSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public const PERMISSION = 'review collection duplicates';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [PermissionEventInterface::EVENT_NAME => 'declare'];
  }

  /**
   * Adds the permission to collections.
   */
  public function declare(PermissionEventInterface $event): void {
    if ($event->getGroupEntityTypeId() !== 'node' || $event->getGroupBundleId() !== 'collection') {
      return;
    }
    $event->setPermissions([new GroupPermission([
      'name' => self::PERMISSION,
      'title' => $this->t('Review possible duplicates of this collection\'s records'),
      'description' => $this->t('Record that one of this collection\'s records is, or is not, the same innovation as another record. Writes a sameInnovationAs claim on this collection\'s record only.'),
    ])]);
  }

}
