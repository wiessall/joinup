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

use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;

/**
 * Import new landing page.
 */
function joinup_core_deploy_108800(): void {
  /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
  $module_installer = \Drupal::service('module_installer');
  /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
  $entity_repository = \Drupal::service('entity.repository');

  // Import new content.
  $module_installer->install(['default_content']);
  /** @var \Drupal\default_content\ImporterInterface $importer */
  \Drupal::service('default_content.importer')->importContent('interoperable_europe', TRUE);
  $module_installer->uninstall(['default_content']);

  // Disable auto-created left-side menu link.
  $landing_page = $entity_repository->loadEntityByUuid('node', '61e83391-9f96-4c9e-995d-9b22705b9f9f');
  $link_plugins = \Drupal::service('plugin.manager.menu.link')->loadLinksByRoute('entity.node.canonical', [
    'node' => $landing_page->id(),
  ], 'ogmenu-3600');
  /** @var \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $link_plugin */
  $link_plugin = reset($link_plugins);
  $entity_repository
    ->loadEntityByUuid('menu_link_content', $link_plugin->getDerivativeId())
    ->setUnpublished()
    ->save();

  // Remove old IOP landing page.
  \Drupal::entityTypeManager()->getStorage('node')->load(704740)->delete();
}

/**
 * Update path alias IOPE.
 */
function joinup_core_deploy_108801(): void {
  /** @var \Drupal\collection\Entity\CollectionInterface $collection */
  $collection = \Drupal::entityTypeManager()
    ->getStorage('rdf_entity')
    ->load(InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID);

  // Get all entities from IOPE.
  $content = $collection->getGroupContentIds();

  $collection->path = ['alias' => '/interoperable-europe', 'pathauto' => 0];
  $collection->save();

  // Update entities in IOPE group.
  foreach ($content as $type => $entities) {
    foreach ($entities as $ids) {
      foreach ($ids as $id) {
        $entity = \Drupal::entityTypeManager()->getStorage($type)->load($id);
        $entity->path = ['pathauto' => 1];
        $entity->save();
      }
    }
  }
}

/**
 * Update the topic vocabulary terms.
 */
function joinup_core_deploy_108802(array &$sandbox): string {
  if (empty($sandbox['tasks'])) {
    $sandbox['tasks'] = [
      'create' => [
        ['Digital government', NULL],
        ['Energy and environment', NULL],
        ['Energy and environment', 'http://joinup.eu/ontology/topic/category#energy-and-environment'],
        ['Smart cities', 'http://joinup.eu/ontology/category#digital-government'],
      ],
      'move_children' => [
        [
          'source' => 'http://joinup.eu/ontology/topic/category#egovernment',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#energy',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#environment',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#industry-and-production',
          'destination' => 'http://joinup.eu/ontology/topic/category#economics',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#information',
          'destination' => 'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#law',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#science-and-technology',
          'destination' => 'http://joinup.eu/ontology/topic/category#education',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#social',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
      ],
      'rename_topic' => [
        [
          'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'Free and open source software',
        ],
        [
          'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'ICT standardisation',
        ],
      ],
      'change_topic_id' => [
        [
          'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations' => 'http://joinup.eu/ontology/topic#free-and-open-source-software',
          'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment' => 'http://joinup.eu/ontology/topic#ict-standardisation',
        ],
      ],
      'change_topic_parent' => [
        [
          [
            'http://joinup.eu/ontology/topic#business-and-competition',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#free-and-open-source-software',
            'http://joinup.eu/ontology/topic/category#ict',
          ],
          [
            'http://joinup.eu/ontology/topic#industry-and-production',
            'http://joinup.eu/ontology/category#economics'
          ],
          [
            'http://joinup.eu/ontology/topic#content-and-knowledge-management',
            'http://joinup.eu/ontology/topic/category#ict',
          ],
          [
            'http://joinup.eu/ontology/topic#statistics',
            'http://joinup.eu/ontology/topic/category#ict',
          ],
          [
            'http://joinup.eu/ontology/topic#ejustice',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#european-policies',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#law',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#eparticipation-and-evoting',
            'http://joinup.eu/ontology/topic/category#education',
          ],
          [
            'http://joinup.eu/ontology/topic#science-and-technology',
            'http://joinup.eu/ontology/topic/category#education',
          ],
          [
            'http://joinup.eu/ontology/topic#demography-and-population',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#einclusion',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#social',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
        ]
      ],
      'update_db_references' => [
        [
          'source' => 'http://joinup.eu/ontology/topic#defence',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#economic-analysis',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#eu-finance',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'destination' => 'http://joinup.eu/ontology/topic#free-and-open-source-software',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'destination' => 'http://joinup.eu/ontology/topic#ict-standardisation',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#open-government',
          'destination' => 'http://joinup.eu/ontology/topic#egovernment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#energy',
          'destination' => 'http://joinup.eu/ontology/topic#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#environment',
          'destination' => 'http://joinup.eu/ontology/topic#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#authorisation-and-authentication',
          'destination' => 'http://joinup.eu/ontology/topic#eidentity-and-esignature',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#information',
          'destination' => 'http://joinup.eu/ontology/topic#content-and-knowledge-management',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#security',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#politics',
          'destination' => 'http://joinup.eu/ontology/topic#european-policies',
        ],
      ],
      'create_redirect' => [
        [
          'source' => 'http://joinup.eu/ontology/topic/category#egovernment',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#energy',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#environment',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#industry-and-production',
          'destination' => 'http://joinup.eu/ontology/topic/category#economics',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#information',
          'destination' => 'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#law',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#science-and-technology',
          'destination' => 'http://joinup.eu/ontology/topic/category#education',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#social',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#defence',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#economic-analysis',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#eu-finance',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'destination' => 'http://joinup.eu/ontology/topic#free-and-open-source-software',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'destination' => 'http://joinup.eu/ontology/topic#ict-standardisation',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#open-government',
          'destination' => 'http://joinup.eu/ontology/topic#egovernment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#energy',
          'destination' => 'http://joinup.eu/ontology/topic#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#environment',
          'destination' => 'http://joinup.eu/ontology/topic#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#authorisation-and-authentication',
          'destination' => 'http://joinup.eu/ontology/topic#eidentity-and-esignature',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#information',
          'destination' => 'http://joinup.eu/ontology/topic#content-and-knowledge-management',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#security',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#politics',
          'destination' => 'http://joinup.eu/ontology/topic#european-policies',
        ],
      ],
      // All terms should be empty in references/children after the migration.
      'delete' => [
        'http://joinup.eu/ontology/topic/category#egovernment',
        'http://joinup.eu/ontology/topic/category#energy',
        'http://joinup.eu/ontology/topic/category#environment',
        'http://joinup.eu/ontology/topic/category#industry-and-production',
        'http://joinup.eu/ontology/topic/category#information',
        'http://joinup.eu/ontology/topic/category#law',
        'http://joinup.eu/ontology/topic/category#science-and-technology',
        'http://joinup.eu/ontology/topic/category#social',
        'http://joinup.eu/ontology/topic#defence',
        'http://joinup.eu/ontology/topic#economic-analysis',
        'http://joinup.eu/ontology/topic#eu-finance',
        'http://joinup.eu/ontology/topic#open-government',
        'http://joinup.eu/ontology/topic#energy',
        'http://joinup.eu/ontology/topic#environment',
        'http://joinup.eu/ontology/topic#authorisation-and-authentication',
        'http://joinup.eu/ontology/topic#information',
        'http://joinup.eu/ontology/topic#security',
        'http://joinup.eu/ontology/topic#politics',
      ],
    ];
  }

  // Helper function to get the internal path of a topic term.
  $get_path = function (string $id): string {
    return \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->load($id)
      ->toUrl()
      ->toString();
  };

  // Helper function to rename a topic.
  $rename_topic = function (string $old_name, string $new_name): void {
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $old_name, 'vid' => 'topic']);
    $term = reset($terms);
    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term->setName($new_name);
    $term->save();
  };

  // Helper function to change the topic URI ID.
  $change_topic_id = function (string $old_id, string $new_id): void {
    $sparql = \Drupal::getContainer()->get('sparql.endpoint');
    $query = <<<QUERY
WITH <http://topic>
DELETE {
  <$old_id> ?p ?o .
}
INSERT {
  <$new_id> ?p ?o .
}
WHERE {
  <$old_id> ?p ?o .
}
QUERY;
    $sparql->query($query);
  };

  // Helper function to change the parent of a topic.
  $change_topic_parent = function (string $id, string $new_parent_id): void {
    $sparql = \Drupal::getContainer()->get('sparql.endpoint');
    $query = <<<QUERY
WITH <http://topic>
DELETE {
  <$id> <http://www.w3.org/2004/02/skos/core#broaderTransitive> ?parent .
}
INSERT {
  <$id> <http://www.w3.org/2004/02/skos/core#broaderTransitive> <{$new_parent_id}> .
}
WHERE {
  <$id> <http://www.w3.org/2004/02/skos/core#broaderTransitive> ?parent .
}
QUERY;
    $sparql->query($query);
  };

  // Helper function to create a redirect between two topics.
  $create_redirect = function (string $old_id, string $new_id): void {
    $redirect = \Drupal::entityTypeManager()
      ->getStorage('redirect')
      ->create([
        'redirect_source' => ['path' => '/taxonomy/term/' . $old_id],
        'redirect_redirect' => ['uri' => 'internal:/taxonomy/term/' . $new_id],
        'language' => 'und',
        'status_code' => 301,
      ]);
    $redirect->save();
  };

  // Helper function to move all topics of a parent to another parent.
  $move_topic_children_to_new_parent = function (string $old_parent_id, string $new_parent_id): void {
    $query = <<<QUERY
WITH <http://topic>
DELETE {
  ?child <http://www.w3.org/2004/02/skos/core#broaderTransitive> <{$old_parent_id}> .
}
INSERT {
  ?child <http://www.w3.org/2004/02/skos/core#broaderTransitive> <{$new_parent_id}> .
}
WHERE {
  ?child <http://www.w3.org/2004/02/skos/core#broaderTransitive> <{$old_parent_id}> .
}
QUERY;
    \Drupal::getContainer()->get('sparql.endpoint')->query($query);
  };

  // Helper function to have all references in SPARQL point from an old to a new
  // topic.
  $update_sparql_entity_references = function (string $old_id, string $new_id): void {
    $graphs = [
      'http://joinup.eu/collection/published',
      'http://joinup.eu/collection/draft',
      'http://joinup.eu/solution/published',
      'http://joinup.eu/solution/draft',
      'http://joinup.eu/asset_release/published',
      'http://joinup.eu/asset_release/draft',
    ];

    $sparql = \Drupal::getContainer()->get('sparql.endpoint');
    foreach ($graphs as $graph) {
      $query = <<<QUERY
WITH <{$graph}>
DELETE {
  ?entity <http://joinup.eu/vocabulary/topic> <{$old_id}> .
}
INSERT {
  ?entity <http://joinup.eu/vocabulary/topic> <{$new_id}> .
}
WHERE {
  ?entity <http://joinup.eu/vocabulary/topic> <{$old_id}> .
}
QUERY;
      $sparql->query($query);
    }
  };

  // Helper function for a direct update of topic references in the SQL
  // database.
  $update_mysql_entity_references = function (string $old_id, string $new_id): void {
    $database = \Drupal::database();

    // Execute the query.
    $database->update('node__field_topic')
      ->fields(['field_topic_target_id' => $new_id])
      ->condition('field_topic_target_id', $old_id)
      ->execute();

    // Do the query also for the revisions table.
    $database->update('node_revision__field_topic')
      ->fields(['field_topic_target_id' => $new_id])
      ->condition('field_topic_target_id', $old_id)
      ->execute();
  };

  // Helper function to create a topic term.
  $create_topic_term = function (string $name, $parent = NULL): void {
    $term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->create([
        'vid' => 'topic',
        'name' => $name,
        'parent' => $parent,
      ]);
    // The ID of the topic will be assigned automatically according to the name.
    // @see \Drupal\joinup_core\Plugin\sparql_entity_storage\Id\TopicVocabulary::generate()
    $term->save();
  };

  // Helper function to delete a topic term.
  $delete_topic_term = function (string $uri): void {
    \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->load($uri)
      ->delete();
  };

  $operation = array_key_first($sandbox['tasks']);
  $tasks = array_splice($sandbox['tasks'], 0, 1);
  foreach ($tasks as $task) {
    switch ($operation) {
      case 'create':
        $create_topic_term($task[0], $task[1]);
        break;

      case 'move_children':
        $move_topic_children_to_new_parent($task['source'], $task['destination']);
        break;

      case 'rename_topic':
        $rename_topic($task[0], $task[1]);
        break;

      case 'change_topic_id':

    }
  }

  if (empty($sandbox['tasks'])) {
    $sandbox['#finished'] = 1;
  }

  return "All topics have been updated";
}
