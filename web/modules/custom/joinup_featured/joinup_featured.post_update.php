<?php

use Drupal\Core\Entity\EntityStorageException;

/**
 * Migrate cached computed field_featured to featured boolean field.
 */
function joinup_featured_post_update_migrate_featured_field(&$sandbox): void {
  $storage = \Drupal::entityTypeManager()->getStorage('meta_entity');

  if (!isset($sandbox['total'])) {
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'featured')
      ->execute();

    $sandbox['ids'] = array_values($ids);
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['current'] = 0;
  }

  $batch_size = 50;
  $ids = array_slice($sandbox['ids'], $sandbox['current'], $batch_size);

  if ($ids) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
    $entities = $storage->loadMultiple($ids);

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
        \Drupal::logger('my_module')->error(
          'Failed to migrate featured value for entity @id: @message',
          [
            '@id' => $entity->id(),
            '@message' => $e->getMessage(),
          ]
        );
      }
    }

    $sandbox['current'] += count($ids);
  }

  if ($sandbox['current'] < $sandbox['total']) {
    $sandbox['#finished'] = $sandbox['current'] / $sandbox['total'];
  }
  else {
    $sandbox['#finished'] = 1;
  }
}
