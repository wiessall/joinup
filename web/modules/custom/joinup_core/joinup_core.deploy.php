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
function joinup_core_deploy_0108200(array &$sandbox) {
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
  foreach ($entity_storage->loadMultiple($nids) as $entity) {
    $field_name = $entity->bundle() === 'collection' ? 'field_ar_contact_information' : 'field_is_contact_information';
    if (!$entity->get($field_name)->isEmpty()) {
      $emails = [];
      foreach ($entity->get($field_name)->referencedEntities() as $contact) {
        if ($email = $contact->get('field_ci_email')->value) {
          \Drupal::service('email_confirmer')->confirm($email);
          $emails[] = $email;
        }
      }
      if ($emails) {
        $account = $entity->getOwner();
        if ($account && $account->isActive()) {
          $mailManager = \Drupal::service('plugin.manager.mail');
          $module = 'joinup_core';
          $key = 'contact_owners_validation';
          $to = $account->getEmail();
          $body_data = [
            '#theme' => 'contact_owners_validation',
            '#name' => $account->label(),
            '#entity_name' => $entity->getName(),
          ];
          $output = \Drupal::service('renderer')->renderPlain($body_data);
          $params['message'] = $output;
          $params['name'] = $entity->getName();
          $langcode = $account->getPreferredLangcode();
          $send = TRUE;

          $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
        }
      }
    }
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Updated {$sandbox['count']} out of {$sandbox['max']} collection/solution pages.";
}
