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
 * Impersonate the passwords of users not linked to EU Login.
 */
function joinup_core_deploy_108202(?array &$sandbox = NULL): void {
  // While using CAS Account Link module, only the paired accounts passwords
  // were impersonated. The other passwords were kept to allow un-paired account
  // users to link their accounts through their Drupal credentials. As the
  // pairing mechanism is being dismantled, we impersonate all other passwords.
  // Not using entity API for performance reasons.
  \Drupal::database()
    ->query("UPDATE {users_field_data}
      SET pass = CONCAT(
        '\$S\$E',
        SUBSTR(
          SHA2(
            CONCAT(name, uid, mail, timezone, UUID()),
            256
          ),
          1,
          51
        )
      )
      WHERE uid NOT IN(SELECT uid FROM {authmap})");
}

/**
 * Validate contact info email.
 */
function joinup_core_deploy_0108203(array &$sandbox) {
  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = \Drupal::entityQuery('rdf_entity')->condition('rid', [
      'collection',
      'solution',
    ], 'IN')->execute();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }
  $entity_storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
  $nids = array_splice($sandbox['ids'], 0, 50);
  $emails = [];
  foreach ($entity_storage->loadMultiple($nids) as $entity) {
    $field_name = $entity->bundle() === 'collection' ? 'field_ar_contact_information' : 'field_is_contact_information';
    if (!$entity->get($field_name)->isEmpty()) {
      foreach ($entity->get($field_name)->referencedEntities() as $contact) {
        if ($email = $contact->get('field_ci_email')->value) {
          $emails[$email]['name'] = $contact->get('field_ci_name')->value;
          $emails[$email]['entities'][] = $entity->label();
        }
      }
    }
  }
  $queue = \Drupal::queue('joinup_email_confirmer');
  $queue->createQueue();
  foreach ($emails as $email => $data) {
    $queue->createItem($email, $data);
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Updated {$sandbox['count']} out of {$sandbox['max']} collection/solution pages.";
}
