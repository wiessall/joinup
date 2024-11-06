<?php

declare(strict_types=1);

namespace Drupal\joinup_user\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\Og;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Show only user content.
 *
 * Alters the Search API query for the user content:
 * - show only the content the user is author of;
 * - do not show entities of some bundles.
 */
class ShowOnlyUserContentSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a ShowOnlyUserContentSubscriber.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected MessengerInterface $messenger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE . '.search_api_field_field_user_content' => [
        ['queryAlter'],
      ],
    ];
  }

  /**
   * Reacts to the query alter.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function queryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();

    /** @var \Drupal\user\Entity\User $user */
    $user = $query->getOption('search_api_field entity');

    // Do not show contact information and owner entities as they are not
    // relevant for the user content, and they are visualized in the related
    // entity "about" page.
    $query->addCondition('entity_bundle', ['contact_information', 'owner'], 'NOT IN');

    $groups = [];
    foreach (Og::getMemberships($user) as $membership) {
      $groups[] = $membership->getGroupId();
    }

    if ($groups) {
      if (count($groups) > 100) {
        $subset = array_chunk($groups, 100);
        $groups = reset($subset);
        // If the user is the owner of the profile, show a warning message.
        if ($user->id() === $this->currentUser->id()) {
          $this->messenger
            ->addWarning($this->t('You have joined a large number of collections and solutions.<br /> Only the content of the first 100 groups is shown.'));
        }
      }
      $or = $query->createConditionGroup('OR');

      // Show all the content the user is author of.
      $or->addCondition('nid', $groups, 'IN');
      // Or all the groups they belong to.
      $or->addCondition('entity_author', $user->id());
      $query->addConditionGroup($or);
    }
    else {
      // Show only content the user is author of.
      $query->addCondition('entity_author', $user->id());
    }

    // Sort content by their creation date.
    $query->sort('entity_created', QueryInterface::SORT_DESC);
  }

}
