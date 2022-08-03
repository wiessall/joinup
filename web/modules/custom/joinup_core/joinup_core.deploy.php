<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

/**
 * Validate contact info email.
 */
function joinup_core_deploy_0108400(?array &$sandbox = NULL): void {
  $rids = \Drupal::entityQuery('rdf_entity')->condition('rid', [
    'contact_information',
  ], 'IN')->execute();
  $entity_storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
  $confirmations = [];
  foreach ($entity_storage->loadMultiple($rids) as $contact) {
    if ($contact->get('field_ci_email')->value) {
      $email = strtolower($contact->get('field_ci_email')->value);
      $confirmations[$email]['email'] = $email;
      $confirmations[$email]['data']['name'] = $contact->get('field_ci_name')->value;
      foreach ($contact->getRelatedGroupsIds() as $group) {
        $entity = $entity_storage->load($group);
        $confirmations[$email]['data']['entities'][] = $entity->label();
      }
    }
  }

  $queue = \Drupal::queue('joinup_email_confirmer');
  $queue->createQueue();
  foreach ($confirmations as $confirmation) {
    $queue->createItem($confirmation);
  }
}
