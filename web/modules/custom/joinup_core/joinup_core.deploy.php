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

/**
 * Change 'culture' to 'culture and environment'.
 */
function joinup_core_deploy_109400(): string {
  // Update the references.
  $vocab_manager = \Drupal::getContainer()->get('topic.vocabulary_manager');
  $vocab_manager->updateDbReference('http://joinup.eu/ontology/topic#culture', 'http://joinup.eu/ontology/topic#culture-and-education');
  return 'Updated the topic vocabulary.';
}

/**
 * Clean up cached_computed_field_expired_fields queue.
 */
function joinup_core_deploy_109401(): void {
  \Drupal::queue('cached_computed_field_expired_fields')->deleteQueue();
}
