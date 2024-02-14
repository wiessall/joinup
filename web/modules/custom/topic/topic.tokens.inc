<?php

/**
 * @file
 * Builds placeholder replacement tokens for meta entity topic.
 */

declare(strict_types=1);

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\topic\Entity\TopicInterface;
use Drupal\topic\Entity\TopicMetaEntityInterface;

/**
 * Implements hook_token_info().
 */
function topic_token_info(): array {
  return [
    'tokens' => [
      'meta_entity' => [
        'parent_topic' => [
          'name' => t('Parent topic term'),
          'description' => t('The parent term of the topic taxonomy term, if one exists.'),
          'module' => 'topic',
          'type' => 'meta_entity',
        ],
      ],
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function topic_tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  $replacements = [];
  if ($type !== 'meta_entity' || empty($data['meta_entity'])) {
    return $replacements;
  }
  $meta_entity = $data['meta_entity'];
  foreach ($tokens as $name => $original) {
    switch ($name) {
      case 'parent_topic':
        if ($meta_entity instanceof TopicMetaEntityInterface && $term = $meta_entity->getTargetEntity()) {
          $term_parent = $term->get('parent');
          // The term will have a reference to root as '0' if it is a root term.
          if (!$term_parent->isEmpty() && (bool) $term_parent->target_id) {
            $parent_topic = $term_parent->entity;
            if ($parent_topic instanceof TopicInterface) {
              $bubbleable_metadata->addCacheableDependency($meta_entity);
              $replacements[$original] = $parent_topic->label();
            }
          }
        }
        break;
    }
  }

  return $replacements;
}
