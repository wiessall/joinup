<?php

/**
 * @file
 * Social share specific procedural functions.
 */

declare(strict_types=1);

/**
 * Preprocess function for the "social_share" theme hook.
 *
 * @param array $variables
 *   An associative array containing:
 *   - entity: the entity being shared.
 *
 * @throws \Drupal\Core\Entity\EntityMalformedException
 *   Thrown when the entity being shared does not support generating a URL.
 */
function ventuno_preprocess_social_share(array &$variables): void {
  /** @var \Drupal\Core\Entity\EntityInterface $entity */
  $entity = $variables['entity'];
  // Generate an entity absolute URL.
  $variables['url'] = $entity->toUrl('canonical', [
    'language' => $entity->language(),
  ])->setAbsolute();

  $variables['encoded_url'] = urlencode($variables['url']->toString());
  $variables['encoded_title'] = urlencode($entity->label());
}
