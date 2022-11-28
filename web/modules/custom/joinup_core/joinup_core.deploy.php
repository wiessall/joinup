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

use Drupal\pathauto\PathautoState;
use Drupal\topic\Entity\TopicInterface;
use Drupal\topic\Entity\TopicMetaEntity;

/**
 * Fix Interoperable Europe news creation link.
 */
function joinup_core_deploy_108900(): void {
  $entityTypeManager = \Drupal::entityTypeManager();
  $entityTypeManager->getStorage('node')->load(704825)->set('path', [
    'pathauto' => PathautoState::SKIP,
    'alias' => '/interoperable-europe/latest-news',
  ])->save();
  if ($alias = $entityTypeManager->getStorage('path_alias')->load(84170)) {
    $alias->delete();
  }
}

/**
 * Synchronize Topic meta entity with Topic terms.
 */
function joinup_core_deploy_108901(): void
{
  // Load the whole taxonomy tree using values.
  $manager = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term');
  $taxonomy_tree = $manager->loadTree('topic', 0, NULL, TRUE);
  foreach ($taxonomy_tree as $term) {
    if ($term instanceof TopicInterface) {
      TopicMetaEntity::create([
        'type' => 'topic',
        'target' => $term,
        'label' => $term->getName(),
        'published' => FALSE,
      ])->save();
    }
  }
}

