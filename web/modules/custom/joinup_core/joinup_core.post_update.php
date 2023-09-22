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

use Drupal\Core\Config\FileStorage;

/**
 * Change the state name of all RDF entities from 'validated' to 'published'.
 */
function joinup_core_post_update_109700(): string {
  $database = \Drupal::getContainer()->get('sparql.endpoint');
  $workflow_urls = [
    'http://joinup.eu/ar/workflow_status' => [
      'http://joinup.eu/collection/published',
      'http://joinup.eu/collection/draft',
    ],
    'http://joinup.eu/contact_information/workflow_status' => [
      'http://joinup.eu/contact-information/published',
    ],
    'http://joinup.eu/is/workflow_status' => [
      'http://joinup.eu/solution/published',
      'http://joinup.eu/solution/draft',
    ],
    'http://joinup.eu/isr/workflow_status' => [
      'http://joinup.eu/asset_distribution/published',
      'http://joinup.eu/asset_distribution/draft',
    ],
    'http://joinup.eu/owner/workflow_status' => [
      'http://joinup.eu/owner/published',
    ],
  ];

  foreach ($workflow_urls as $predicate => $graphs) {
    foreach ($graphs as $graph) {
      $update_query = <<<SPARQL
WITH <{$graph}>
DELETE {
  ?s <{$predicate}> "validated"^^<http://www.w3.org/2001/XMLSchema#string> .
}
INSERT {
  ?s <{$predicate}> "published"^^<http://www.w3.org/2001/XMLSchema#string> .
}
WHERE {
  ?s <{$predicate}> "validated"^^<http://www.w3.org/2001/XMLSchema#string> .
}
SPARQL;
      $database->query($update_query);
    }
  }

  return 'Changed the state name of all RDF entities from "validated" to "published".';
}

/**
 * Change the state name of all Node entities from 'validated' to 'published'.
 */
function joinup_core_post_update_109701(): string {
  \Drupal::getContainer()->get('database')->update('node__field_state')
    ->fields(['field_state_value' => 'published'])
    ->condition('field_state_value', 'validated')
    ->execute();

  // Take care of the revisions.
  \Drupal::getContainer()->get('database')->update('node_revision__field_state')
    ->fields(['field_state_value' => 'published'])
    ->condition('field_state_value', 'validated')
    ->execute();

  return 'Changed the state name of all nodes from "validated" to "published".';
}

/**
 * Sync user.role.moderator ahead of config import.
 */
function joinup_core_post_update_109702(): void {
  // The user role moderator had a dependency on the tallinn module as it
  // contained a permission that was defined there. In order to remove the
  // dependency we need to sync the config before the import because in another
  // update we updated some permission names and the config import would fail
  // because of that. The reason is that removing the dependency, sets the
  // priority of the import in a different way, where the role is not forced to
  // be updated *before* the uninstall of the tallinn module. Thus, tallinn is
  // uninstalled first and the dependencies are updated. The role has a
  // dependency on the tallinn module in the active storage, thus is loaded and
  // re-saved. During the re-save, the role is building and validating the
  // permissions. The permissions are constructed using the new permission names
  // but the role loaded from the active storage is using the old permission
  // names. Thus, the validation fails.
  $source = new FileStorage('../config/sync');
  /** @var \Drupal\Core\Config\StorageInterface $active_storage */
  $active_storage = \Drupal::service('config.storage');
  $active_storage->write('user.role.moderator', $source->read('user.role.moderator'));
}

/**
 * Clean tallinn data.
 */
function joinup_core_post_update_109703(): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $entities = $storage->loadByProperties([
    'type' => 'tallinn_report',
  ]);
  $storage->delete($entities);

  return 'Tallinn data cleaned';
}
