<?php

declare(strict_types=1);

namespace Drupal\joinup_subscription;

use Drupal\joinup_community_content\CommunityContentHelper;

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
      'rdf_entity' => ['solution'],
      'node' => CommunityContentHelper::BUNDLES,
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

  /**
   * Provides data for a field based on the group type.
   *
   * @param string $groupType
   *   The group type.
   *
   * @return array
   *   The array of data for the field.
   */
  static function provideDataForField(string $groupType): array {
    $contentBundles = self::SUBSCRIPTION_BUNDLES[$groupType] ?? [];

    $value = [];
    foreach ($contentBundles as $entityType => $bundles) {
      foreach ($bundles as $bundle) {
        $value[] = [
          'entity_type' => $entityType,
          'bundle' => $bundle,
        ];
      }
    }

    return $value;
  }

}
