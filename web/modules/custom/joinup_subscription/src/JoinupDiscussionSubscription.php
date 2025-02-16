<?php

declare(strict_types=1);

namespace Drupal\joinup_subscription;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Entity\Flag;
use Drupal\flag\FlagServiceInterface;
use Drupal\joinup_subscription\Exception\UserAlreadySubscribedException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a service to handle subscriptions to discussions.
 */
readonly class JoinupDiscussionSubscription implements JoinupDiscussionSubscriptionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'flag')]
    protected FlagServiceInterface $flagService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getSubscribers(ContentEntityInterface $entity, string $flag_id): array {
    if (!Flag::load($flag_id)) {
      throw new \InvalidArgumentException("Flag with ID '$flag_id' doesn't exist");
    }

    $flagging_storage = $this->entityTypeManager->getStorage('flagging');
    $flaggings = $flagging_storage->getQuery()->accessCheck(FALSE)
      ->condition('flag_id', $flag_id)
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->condition('uid', 0, '>')
      ->execute();

    $subscribers = [];
    if ($flaggings) {
      $uids = [];
      /** @var \Drupal\flag\FlaggingInterface $flagging */
      foreach ($flagging_storage->loadMultiple($flaggings) as $flagging) {
        $uids[] = $flagging->getOwnerId();
      }
      if ($uids) {
        $subscribers = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);

        // Flaggings may be orphaned if the user has been deleted. Filter out
        // any non-existing users.
        $subscribers = array_filter($subscribers);
      }
    }

    return $subscribers;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe(AccountInterface $account, ContentEntityInterface $entity, string $flag_id): bool {
    assert(!$account->isAnonymous(), 'Only authenticated users can subscribe to discussions.');

    $flag = $this->flagService->getFlagById($flag_id);
    // Throw an exception when the user is already subscribed, so the calling
    // code can generate an appropriate response.
    if ($flag->isFlagged($entity, $account)) {
      $account_name = $account->getAccountName();
      $entity_type = $entity->getEntityTypeId();
      $entity_label = $entity->label();
      throw new UserAlreadySubscribedException("The user '$account_name' is already subscribed to the $entity_type entity with label '$entity_label'.");
    }
    else {
      $flagging = $this->flagService->flag($flag, $entity, $account);
    }

    return !empty($flagging);
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe(AccountInterface $account, ContentEntityInterface $entity, string $flag_id): void {
    $flag = $this->flagService->getFlagById($flag_id);
    $this->flagService->unflag($flag, $entity, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function isSubscribed(AccountInterface $account, ContentEntityInterface $entity, string $flag_id): bool {
    $flag = $this->flagService->getFlagById($flag_id);
    return (bool) $this->flagService->getFlagging($flag, $entity, $account);
  }

}
