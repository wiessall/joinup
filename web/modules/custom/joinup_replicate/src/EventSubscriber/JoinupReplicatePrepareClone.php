<?php

declare(strict_types=1);

namespace Drupal\joinup_replicate\EventSubscriber;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\replicate\Events\ReplicateAlterEvent;
use Drupal\replicate\Events\ReplicatorEvents;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets the workflow status of a replicated entity to draft.
 */
class JoinupReplicatePrepareClone implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ReplicatorEvents::REPLICATE_ALTER][] = ['setToDraft', 3];
    $events[ReplicatorEvents::REPLICATE_ALTER][] = ['emptyPublicationDate', 1];
    $events[ReplicatorEvents::REPLICATE_ALTER][] = ['setOwner'];
    $events[ReplicatorEvents::REPLICATE_ALTER][] = ['setRevisionLog'];

    return $events;
  }

  /**
   * Sets the workflow status of a replicated entity to draft.
   *
   * @param \Drupal\replicate\Events\ReplicateAlterEvent $event
   *   The event fired by Replicator.
   *
   * @throws \Exception
   *   If entity is moderated, but setting it as draft is impossible.
   */
  public function setToDraft(ReplicateAlterEvent $event): void {
    $clone = $event->getEntity();
    $clone->skip_notification = TRUE;
    if (!$clone instanceof EntityWorkflowStateInterface) {
      return;
    }
    $clone->setWorkflowState('draft');
  }

  /**
   * Sets the current user account as the owner of the content.
   *
   * @param \Drupal\replicate\Events\ReplicateAlterEvent $event
   *   The event fired by Replicator.
   */
  public function setOwner(ReplicateAlterEvent $event): void {
    $clone = $event->getEntity();
    if (!$clone instanceof EntityOwnerInterface) {
      return;
    }

    $clone->setOwnerId($this->currentUser->id());
  }

  /**
   * Sets a revision log message about cloning.
   *
   * @param \Drupal\replicate\Events\ReplicateAlterEvent $event
   *   The event fired by Replicator.
   */
  public function setRevisionLog(ReplicateAlterEvent $event): void {
    $clone = $event->getEntity();
    if (!$clone instanceof RevisionLogInterface) {
      return;
    }

    $clone->setRevisionLogMessage($this->t('Replicated by account with ID @account-id', [
      '@account-id' => $this->currentUser->id(),
    ]));
  }

  /**
   * Empties the publication date field if needed.
   *
   * @param \Drupal\replicate\Events\ReplicateAlterEvent $event
   *   The event fired by Replicator.
   */
  public function emptyPublicationDate(ReplicateAlterEvent $event): void {
    $clone = $event->getEntity();

    if ($clone instanceof GroupContentInterface) {
      $clone->set('published_at', NULL);
    }
  }

}
