<?php

/**
 * @file
 * Builds placeholder replacement tokens for joinup group related data.
 */

declare(strict_types=1);

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\joinup_collection\Entity\CollectionContentInterface;
use Drupal\joinup_collection\Entity\CollectionInterface;
use Drupal\joinup_collection\Exception\MissingCollectionException;
use Drupal\joinup_bundle_class\ShortIdInterface;
use Drupal\joinup_solution\Entity\SolutionContentInterface;
use Drupal\joinup_solution\Exception\MissingSolutionException;

/**
 * Implements hook_token_info().
 */
function joinup_group_token_info(): array {
  return [
    'tokens' => [
      'node' => [
        'parent_collection' => [
          'name' => t('Parent collection'),
          'description' => t('Returns the parent collection for any distribution.'),
          'module' => 'joinup_group',
          'type' => 'node',
        ],
        'short_id_or_title' => [
          'name' => t('Short ID or title'),
          'description' => t('Returns short ID if available, or the title if the short ID is not.'),
          'module' => 'joinup_group',
          'type' => 'node',
        ],
      ],
      'rdf_entity' => [
        'parent_collection' => [
          'name' => t('Parent collection'),
          'description' => t('Returns the parent collection for any solution or release.'),
          'module' => 'joinup_group',
          'type' => 'rdf_entity',
        ],
        // @todo Remove this in ISAICP-8574.
        'short_id_or_title' => [
          'name' => t('Short ID or title'),
          'description' => t('Returns short ID if available, or the title if the short ID is not.'),
          'module' => 'joinup_group',
          'type' => 'rdf_entity',
        ],
      ],
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function joinup_group_tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  $replacements = [];
  if (!in_array($type, ['node', 'rdf_entity'], TRUE) || empty($data[$type])) {
    return $replacements;
  }
  $token_service = \Drupal::token();
  $entity = $data[$type];

  $get_parent_collection = function (CollectionContentInterface $entity): ?CollectionInterface {
    try {
      return $entity->getCollection();
    }
    catch (MissingCollectionException $e) {
      // Unexpected case of a missing collection for group content. Let's log an
      // error but this is not fatal so allow the token generation to continue.
      \Drupal::logger('joinup_group')->error('Failed to generate token for @type entity with ID @id due to missing parent collection', [
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]);
    }
    return NULL;
  };

  foreach ($tokens as $name => $original) {
    switch ($name) {
      case 'parent_collection':
        // If the entity is solution content then it is related to the parent
        // collection through the solution. Add a dependency on the solution
        // because if the solution moves to a different collection this affects
        // the token value.
        if ($entity instanceof SolutionContentInterface) {
          try {
            $bubbleable_metadata->addCacheableDependency($entity->getSolution());
          }
          catch (MissingSolutionException $e) {
            // It is not expected that the solution is missing for solution
            // content. Let's log an error but this is not fatal so allow the
            // token generation to continue.
            \Drupal::logger('joinup_group')->error('Failed to generate token for @type entity with ID @id due to missing parent solution', [
              '@type' => $entity->getEntityTypeId(),
              '@id' => $entity->id(),
            ]);
            break;
          }
        }
        if ($entity instanceof CollectionContentInterface && $parent_collection = $get_parent_collection($entity)) {
          $bubbleable_metadata->addCacheableDependency($parent_collection);
          $replacements[$original] = $parent_collection->label();
        }
        break;

      case 'short_id_or_title':
        $replacements[$original] = ($entity instanceof ShortIdInterface && $entity->hasShortId())
          ? $entity->getShortId()
          : $entity->label();
        $bubbleable_metadata->addCacheableDependency($entity);
        break;
    }
  }

  // Allow to descend further into the solution's parent collection.
  if (($collection_tokens = $token_service->findWithPrefix($tokens, 'parent_collection')) && $entity instanceof CollectionContentInterface && $collection = $get_parent_collection($entity)) {
    $replacements += $token_service->generate('rdf_entity', $collection_tokens, ['rdf_entity' => $collection], $options, $bubbleable_metadata);
  }

  return $replacements;
}
