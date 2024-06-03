<?php

declare(strict_types=1);

namespace Drupal\joinup_replicate\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\replicate\Events\AfterSaveEvent;
use Drupal\replicate\Events\ReplicateAlterEvent;
use Drupal\replicate\Events\ReplicatorEvents;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Sets the workflow status of a replicated entity to draft.
 */
class JoinupReplicatePrepareClone implements EventSubscriberInterface {

  public function __construct(
    protected AccountInterface $currentUser,
    protected TimeInterface $timeService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ReplicatorEvents::REPLICATE_ALTER][] = ['setToDraft', 2];
    $events[ReplicatorEvents::REPLICATE_ALTER][] = ['emptySharedInField', 1];
    $events[ReplicatorEvents::REPLICATE_ALTER][] = ['setOwner'];
    // @todo Remove this in ISAICP-8776.
    $events[ReplicatorEvents::REPLICATE_ALTER][] = ['setUnpublished', 1];
    $events[ReplicatorEvents::AFTER_SAVE][] = ['makeRedirect', 1];

    return $events;
  }

  /**
   * Sets the status of a replicated node to unpublished.
   *
   * @param \Drupal\replicate\Events\ReplicateAlterEvent $event
   *   The event fired by the replicator.
   *
   * @todo Remove this in ISAICP-8776 after Replicate Actions is verified by QA.
   */
  public function setUnpublished(ReplicateAlterEvent $event): void {
    $clonedEntity = $event->getEntity();

    // Don't allow the paragraph entities to be unpublished.
    if ($clonedEntity->getEntityTypeId() == 'paragraph') {
      return;
    }

    // Don't allow non-reusable block content entities (from Layout Builder)
    // to be unpublished.
    if ($clonedEntity->getEntityTypeId() === 'block_content' && !$clonedEntity->isReusable()) {
      return;
    }

    if ($this->isPublishableType($clonedEntity)) {

      // We don't need to check if it's instanceof TranslatableInterface since
      // at this point it's guaranteed to be a content entity (which implement
      // TranslatableInterface). If the above check changes in the future,
      // we would need to make sure this is translatable before getting
      // languages.
      foreach ($clonedEntity->getTranslationLanguages() as $translation_language) {
        /**
         * @var \Drupal\Core\Entity\ContentEntityTypeInterface|\Drupal\Core\Entity\EntityPublishedInterface $translation
         */
        $translation = $clonedEntity->getTranslation($translation_language->getId());
        $translation->setUnpublished();

        // Set the correct created/changed dates.
        //
        // There is currently no interface or other object-oriented indicator
        // that an entity has the concept of created/changed times.
        // See https://www.drupal.org/project/drupal/issues/2833378.
        $current_time = $this->timeService->getCurrentTime();
        if (method_exists($clonedEntity, 'setCreatedTime')) {
          $translation->setCreatedTime($current_time);
        }
        if (method_exists($clonedEntity, 'setChangedTime')) {
          $translation->setChangedTime($current_time);
        }
      }
    }
  }

  /**
   * Make a redirect to "edit" mode.
   *
   * @param \Drupal\replicate\Events\AfterSaveEvent $event
   *   The event fired by the replicator.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *
   * @todo Remove this in ISAICP-8776 after Replicate Actions is verified by QA.
   */
  public function makeRedirect(AfterSaveEvent $event): void {
    $clonedEntity = $event->getEntity();
    $entityType = $clonedEntity->getEntityTypeId();

    try {
      if ($this->isPublishableType($clonedEntity)) {
        $editFormUrl = $clonedEntity->toUrl('edit-form');
        $edit_form_url = Url::fromRoute($editFormUrl->getRouteName(), [
          $entityType => $clonedEntity->id(),
        ]);
        $response = new RedirectResponse($edit_form_url->toString());
        $response->send();
      }
    }
    catch (UndefinedLinkTemplateException $e) {
      // Do nothing for this exception, it's acceptable to throw it away.
    }
  }

  /**
   * Helper method to report if the provided entity supports published status.
   *
   * @param \Drupal\Core\Entity\EntityInterface $clonedEntity
   *   The cloned entity to check.
   *
   * @return bool
   *   TRUE if published status is supported, FALSE otherwise.
   *
   * @todo Remove this in ISAICP-8776 after Replicate Actions is verified by QA.
   */
  protected function isPublishableType(EntityInterface $clonedEntity): bool {
    $publishable = FALSE;
    if ($clonedEntity instanceof ContentEntityInterface && $clonedEntity instanceof EntityPublishedInterface) {
      $publishable = TRUE;
    }
    return $publishable;
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
   * Empties the shared in field if needed.
   *
   * @param \Drupal\replicate\Events\ReplicateAlterEvent $event
   *   The event fired by Replicator.
   */
  public function emptySharedInField(ReplicateAlterEvent $event): void {
    $original = $event->getOriginal();
    if (
      $original instanceof GroupContentInterface &&
      $original->getSharedInField() &&
      !$original->getSharedInField()->isEmpty()
    ) {
      $clone = $event->getEntity();
      assert($clone instanceof GroupContentInterface);
      $clone->getSharedInField()?->delete();
    }
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

}
