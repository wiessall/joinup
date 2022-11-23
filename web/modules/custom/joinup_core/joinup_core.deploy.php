<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\taxonomy\Entity\Term;
use Drupal\topic\Entity\TopicInterface;

/**
 * Synchronize Topic meta entity with Topic terms.
 */
function joinup_core_deploy_108802(): void {
  // Load the whole taxonomy tree using values.
  $manager = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term');
  $taxonomy_tree = $manager->loadTree('topic');

  foreach ($taxonomy_tree as $term) {
    $term = Term::load($term->tid);
    if ($term instanceof TopicInterface) {
      $topic = MetaEntity::create([
        'type' => 'topic',
        'target' => $term,
        'label' => $term->getName(),
        'published' => TRUE,
      ]);
      $topic->save();
    }
  }
}
