<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Core\Url;
use Drupal\joinup_group\Entity\AnnouncementInterface;
use Drupal\message_notify\Event\MessageNotifyDeliveryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Acts before the announcements are delivered.
 */
class AnnouncementDeliverySubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MessageNotifyDeliveryEvent::class => 'onAnnouncementDelivery',
    ];
  }

  /**
   * Adds per-recipient arguments to the message.
   *
   * @param \Drupal\message_notify\Event\MessageNotifyDeliveryEvent $event
   *   The message notify delivery event instance.
   */
  public function onAnnouncementDelivery(MessageNotifyDeliveryEvent $event): void {
    $notifier = $event->getNotifier();
    if ($notifier->getPluginId() === 'email') {
      $announcement = $event->getMessage();
      if ($announcement instanceof AnnouncementInterface) {
        $email = $notifier->getConfiguration()['mail'] ?: NULL;
        if (!$email) {
          throw new \LogicException("The message 'email' notifier plugin cannot be instantiated without the 'mail' configuration");
        }

        if (!$account = user_load_by_mail($email)) {
          // Maybe the user has been deleted in the meantime? Exit silently.
          return;
        }

        // Enrich the announcement arguments with recipient data.
        $arguments = $announcement->getArguments();
        $arguments['@recipient_name'] = $account->getDisplayName();
        $arguments['@recipient_profile'] = $account->toUrl()->setAbsolute()->toString();
        $arguments['@recipient_profile_edit'] = $account->toUrl('edit-form')->setAbsolute()->toString();
        $arguments['@recipient_subscriptions'] = Url::fromRoute('joinup_subscription.subscriptions', [
          'user' => $account->id(),
        ])->setAbsolute()->toString();
        $arguments['@recipient_unsubscribe'] = Url::fromRoute('joinup_subscription.unsubscribe', [
          'user' => $account->id(),
          'group' => $announcement->getGroup()->id(),
          'types' => 'announcements',
        ])->setAbsolute()->toString();
        $announcement->setArguments($arguments);
      }
    }
  }

}
