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
 * Queue contact information emails for validation.
 */
function joinup_core_deploy_108500(array &$sandbox): void {
  $queue = \Drupal::queue('joinup_email_confirmer');
  $queue->createQueue();

  /** @var \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparql */
  $sparql = \Drupal::service('sparql.endpoint');
  $rows = $sparql->query('SELECT DISTINCT(?email)
    WHERE {
      GRAPH <http://joinup.eu/contact-information/published> {
        ?id <http://www.w3.org/2006/vcard/ns#hasEmail> ?email .
        ?id a <http://www.w3.org/2006/vcard/ns#Kind> .
      }
    }
    ORDER BY (?email)')->getArrayCopy();
  array_walk($rows, function (\stdClass $row) use ($queue): void {
    $queue->createItem(['email' => $row->email->getValue()]);
  });
}
