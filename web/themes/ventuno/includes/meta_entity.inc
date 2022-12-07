<?php

/**
 * @file
 * Meta Entity specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_meta_entity_alter(array &$suggestions, array $variables): void {
  _ventuno_theme_suggestions_alter($suggestions, $variables);
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_meta_entity__topic__teaser(array &$variables): void {
  /** @var \Drupal\meta_entity\Entity\MetaEntityInterface $meta_entity */
  $meta_entity = $variables['meta_entity'];
  $topic = $meta_entity->getTargetEntity();
  $variables['target_entity_url'] = $topic->toUrl()->setAbsolute()->toString();
}
