<?php

/**
 * @file
 * Deploy hooks for Joinup Featured module.
 */

declare(strict_types=1);

use Drupal\Core\Entity\EntityStorageException;

/**
 * Migrate cached computed field_featured to featured boolean field.
 */
function joinup_featured_deploy_201501(): void {
  $storage = \Drupal::entityTypeManager()->getStorage('meta_entity');

  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'featured')
    ->execute();

  if (empty($ids)) {
    return;
  }

  $batch_size = 50;
  $ids = array_values($ids);
  $chunks = array_chunk($ids, $batch_size);

  foreach ($chunks as $chunk) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
    $entities = $storage->loadMultiple($chunk);

    foreach ($entities as $entity) {
      if ($entity->get('field_featured')->isEmpty()) {
        continue;
      }

      $entity->set(
        'featured',
        (bool) $entity->get('field_featured')->value
      );

      try {
        $entity->save();
      }
      catch (EntityStorageException $e) {
        \Drupal::logger('joinup_featured')->error(
          'Failed to migrate featured value for entity @id: @message',
          [
            '@id' => $entity->id(),
            '@message' => $e->getMessage(),
          ]
        );
      }
    }
  }
}
