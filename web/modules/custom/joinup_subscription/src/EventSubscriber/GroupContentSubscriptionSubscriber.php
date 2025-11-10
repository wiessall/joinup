<?php

declare(strict_types=1);

namespace Drupal\joinup_subscription\EventSubscriber;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\collection\Entity\CollectionContentInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
use Drupal\joinup_notification\NotificationEvents;
use Drupal\joinup_subscription\Entity\GroupContentSubscriptionMessage;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\og\OgMembershipInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for compiling group content subscription digest messages.
 */
readonly class GroupContentSubscriptionSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected JoinupMessageDeliveryInterface $messageDelivery,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      NotificationEvents::NODE_CREATE => 'notifyOnCommunityContentCreation',
      NotificationEvents::NODE_UPDATE => 'notifyOnCommunityContentPublication',
    ];
  }

  /**
   * Notifies subscribed users when new community content is published.
   */
  public function notifyOnCollectionContentCreation(NotificationEvent $event): void {
    $entity = $this->getEligibleEntity($event);
    if (!$entity) {
      return;
    }

    // Only notify if the newly created content is published.
    if (!$entity->isPublished()) {
      return;
    }

    $this->sendMessage($entity);
  }

  /**
   * Notifies subscribed users when existing community content is published.
   */
  public function notifyOnCollectionContentPublication(NotificationEvent $event): void {
    $entity = $this->getEligibleEntity($event);
    if (!$entity) {
      return;
    }

    // Only notify if the content is being published for the first time.
    if (!$entity->isPublished() || empty($entity->original) || $entity->original->isPublished() || !$this->isFirstPublishedRevision($entity)) {
      return;
    }

    $this->sendMessage($entity);
  }

  /**
   * Returns the entity if it qualifies for notification, NULL otherwise.
   */
  private function getEligibleEntity(NotificationEvent $event): ?CollectionContentInterface {
    $entity = $event->getEntity();

    if (!$entity instanceof CollectionContentInterface) {
      return NULL;
    }

    // Ignore archived content.
    if ($entity instanceof ArchivableEntityInterface && $entity->isArchived()) {
      return NULL;
    }

    // Ignore orphaned group content.
    if (!$entity->hasGroup()) {
      return NULL;
    }

    // Ignore content that is not approved for inclusion in digest messages.
    $group_bundle = $entity->getGroup()->bundle();
    $subscribable_bundles = JoinupSubscriptionsHelper::SUBSCRIPTION_BUNDLES[$group_bundle]['node'] ?? [];
    if (!in_array($entity->bundle(), $subscribable_bundles, TRUE)) {
      return NULL;
    }

    return $entity;
  }

  /**
   * Returns the list of subscribers.
   *
   * @param \Drupal\joinup_group\Entity\GroupContentInterface $entity
   *   The group content entity for which to return the subscribers.
   *
   * @return \Drupal\user\UserInterface[]
   *   The list of subscribers as an array of user accounts, keyed by user ID.
   */
  protected function getSubscribers(GroupContentInterface $entity): array {
    $membership_storage = $this->entityTypeManager->getStorage('og_membership');
    $membership_ids = $membership_storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $entity->getGroupId())
      ->condition('state', OgMembershipInterface::STATE_ACTIVE)
      ->condition('subscription_bundles', $entity->bundle())
      ->execute();

    // We're loading the full membership entities in order to extract the user
    // IDs. Since subscriptions are disabled by default we do not expect them to
    // become hugely popular, but if they do this can possibly be optimized by
    // bypassing the entity API and doing a direct SELECT query.
    $memberships = $membership_storage->loadMultiple($membership_ids);
    $user_ids = array_map(function (OgMembershipInterface $membership): int {
      return (int) $membership->getOwnerId();
    }, $memberships);

    return $this->entityTypeManager->getStorage('user')->loadMultiple($user_ids);
  }

  /**
   * Sends the message to the recipients.
   *
   * These are in fact not sent but stored in the database for later sending in
   * a digest message.
   *
   * @param \Drupal\joinup_group\Entity\GroupContentInterface $group_content
   *   The group content for which to send messages.
   *
   * @return bool
   *   Whether or not the sending of the messages has succeeded.
   */
  protected function sendMessage(GroupContentInterface $group_content): bool {
    try {
      $success = TRUE;
      // Create individual messages for each subscriber so that we can honor the
      // user's chosen digest frequency.
      foreach ($this->getSubscribers($group_content) as $subscriber) {
        $message = GroupContentSubscriptionMessage::create([
          'group_content' => [
            0 => [
              'target_id' => $group_content->id(),
            ],
          ],
        ]);
        $success = $this->messageDelivery->sendMessageToUser($message, $subscriber, [], TRUE) && $success;
      }
      return $success;
    }
    catch (\Exception $e) {
      $context = ['exception' => $e];
      $this->loggerFactory->get('mail')->critical('Unexpected ' . get_class($e) . ' thrown in ' . $e->getFile() . ' on line ' . $e->getLine() . ' when sending a group content subscription message.', $context);
      return FALSE;
    }
  }

  /**
   * Returns whether the passed in entity is the first published revision.
   *
   * @param \Drupal\Core\Entity\EntityPublishedInterface|\Drupal\Core\Entity\RevisionableInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the current revision of the entity is the first published
   *   revision.
   */
  protected function isFirstPublishedRevision(EntityPublishedInterface $entity): bool {
    return $entity->getRevisionId() == $this->getFirstPublishedRevisionId($entity);
  }

  /**
   * Returns the ID of the first published revision of the given entity.
   *
   * @param \Drupal\Core\Entity\EntityPublishedInterface $entity
   *   The entity for which to return the first published revision ID.
   *
   * @return mixed|null
   *   The revision ID, or NULL if there is no published revision.
   */
  protected function getFirstPublishedRevisionId(EntityPublishedInterface $entity): mixed {
    $entity_type = $entity->getEntityTypeId();
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $definition = $this->entityTypeManager->getDefinition($entity_type);

    $revision_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($definition->getKey('id'), $entity->id())
      ->condition($definition->getKey('published'), 1)
      ->sort($definition->getKey('revision'), 'ASC')
      ->range(0, 1)
      ->execute();
    reset($revision_ids);
    return !empty($revision_ids) ? key($revision_ids) : NULL;
  }

}
