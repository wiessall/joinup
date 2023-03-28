<?php

/**
 * @file
 * Post update functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API but
 * need to run _before_ the configuration is imported.
 *
 * For example this can be used to enable a new module that needs to have its
 * code available for the configuration to be successfully imported or updated.
 *
 * In most cases though update code should be placed in joinup_core.deploy.php.
 */

declare(strict_types = 1);

use Drupal\contact_information\ContactInformationEmailConfirmInterface;
use Drupal\email_confirmer\EmailConfirmationInterface;

/**
 * Clean up the email confirmations.
 */
function joinup_core_post_update_109100(array &$sandbox): string {
  if (empty($sandbox['emails'])) {
    // Select email confirmations from the email_confirmation table, that has
    // the status to 1, with the same `realm` where there are more than one
    // confirmed confirmations. Return the `email` column to load the
    // confirmations by it.
    $query = \Drupal::database()->select('email_confirmer_confirmation', 'ec')
      ->fields('ec', ['email'])
      ->condition('ec.status', 1)
      ->condition('ec.confirmed', 1)
      ->condition('ec.realm', ContactInformationEmailConfirmInterface::REALM)
      ->groupBy('ec.email')
      ->having('COUNT(ec.email) > 1');

    $sandbox['emails'] = $query->execute()->fetchCol();
    $sandbox['current'] = 0;
    $sandbox['total'] = count($sandbox['emails']);
  }

  $limit = 50;
  $emails = array_slice($sandbox['emails'], $sandbox['current'], $limit);
  $sandbox['current'] += count($emails);
  $email_confirmer = \Drupal::getContainer()->get('email_confirmer');

  foreach ($emails as $email) {
    $email_confirmations = $email_confirmer->getConfirmations($email, EmailConfirmationInterface::CONFIRMED, 0, ContactInformationEmailConfirmInterface::REALM);
    // Failsafe check.
    if (count($email_confirmations) <= 1) {
      throw new \Exception('Attempt to run this on a single confirmation.');
    }

    // Prepare for deletion only the confirmations with IP set to '127.0.0.1'.
    $email_confirmations_to_delete = array_filter($email_confirmations, function (EmailConfirmationInterface $email_confirmation): bool {
      return $email_confirmation->getIp() === '127.0.0.1';
    });

    // If the number of confirmations to delete is the same as the initial
    // confirmations, then keep the first one. Otherwise, delete all of them.
    // This is to ensure that if our code created more than one confirmation
    // but the user did not create one manually, then still keep one so that
    // the email is confirmed.
    if (count($email_confirmations_to_delete) === count($email_confirmations)) {
      array_shift($email_confirmations_to_delete);
    }

    // Delete the confirmations.
    foreach ($email_confirmations_to_delete as $email_confirmation) {
      $email_confirmation->delete();
    }
  }

  $sandbox['#finished'] = empty($sandbox['emails']) ? 1 : ($sandbox['current'] / $sandbox['total']);
  return "Cleaned up {$sandbox['current']} email confirmations.";
}
