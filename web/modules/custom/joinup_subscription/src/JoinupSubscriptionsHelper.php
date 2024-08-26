<?php

declare(strict_types=1);

namespace Drupal\joinup_subscription;

/**
 * Helper class for Joinup subscriptions.
 */
class JoinupSubscriptionsHelper {

  /**
   * An array of group content bundles that can be subscribed to.
   *
   * Users subscribing to collections or solutions can opt to receive updates
   * about all community content, and collection subscribers can also get
   * information about new solutions that are published in the collection.
   */
  const array SUBSCRIPTION_BUNDLES = [
    'collection' => [
      'node' => [
        'discussion',
        'distribution',
        'document',
        'event',
        'news',
        'solution',
      ],
    ],
    'solution' => [
      'node' => [
        'discussion',
        'distribution',
        'document',
        'event',
        'news',
        'release',
      ],
    ],
  ];

}
