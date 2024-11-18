<?php

/**
 * @file
 * Builds placeholder replacement tokens for joinup group related data.
 */

declare(strict_types=1);

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\joinup_bundle_class\ShortIdInterface;

/**
 * Implements hook_token_info().
 */
function joinup_group_token_info(): array {
  return [
    'tokens' => [
      'node' => [
        'short_id_or_title' => [
          'name' => t('Short ID or title'),
          'description' => t('Returns short ID if available, or the title if the short ID is not.'),
          'module' => 'joinup_group',
          'type' => 'node',
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

  if ($type === 'node' && !empty($data[$type]) && isset($tokens['short_id_or_title'])) {
    $entity = $data[$type];
    $replacements[$tokens['short_id_or_title']] = $entity instanceof ShortIdInterface && $entity->hasShortId()
      ? $entity->getShortId()
      : $entity->label();
    $bubbleable_metadata->addCacheableDependency($entity);
  }

  return $replacements;
}
