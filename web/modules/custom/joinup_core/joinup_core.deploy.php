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

  // *** Changes to the parent terms ***
  // - 'eGovernment' renamed to 'Digital government'.
  // Mainly create, and move children to the new parent.
  // eGovernment URI: http://joinup.eu/ontology/topic/category#egovernment
  // 'Digital government' URI:
  // http://joinup.eu/ontology/topic/category#digital-government
  // Redirects to be created.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic/category#egovernment');
  $create_topic_term('Digital government');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic/category#digital-government');
  $move_topic_children_to_new_parent('http://joinup.eu/ontology/topic/category#egovernment', 'http://joinup.eu/ontology/topic/category#digital-government');
  $delete_topic_term('http://joinup.eu/ontology/topic/category#egovernment');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Energy' Combined with 'Environment'.
  // Energy URI: http://joinup.eu/ontology/topic/category#energy
  // A new category 'Energy and environment' will be created.
  // The children of 'Energy' will be moved to the new category.
  // The children of 'Environment' will be moved to the new category.
  // Redirects to be created from both categories.
  $old_energy_path = $get_path('http://joinup.eu/ontology/topic/category#energy');
  $old_environment_path = $get_path('http://joinup.eu/ontology/topic/category#environment');
  $create_topic_term('Energy and environment');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic/category#energy-and-environment');
  $move_topic_children_to_new_parent('http://joinup.eu/ontology/topic/category#energy', 'http://joinup.eu/ontology/topic/category#energy-and-environment');
  $move_topic_children_to_new_parent('http://joinup.eu/ontology/topic/category#environment', 'http://joinup.eu/ontology/topic/category#energy-and-environment');
  $create_redirect($old_energy_path, $new_internal_path);
  $create_redirect($old_environment_path, $new_internal_path);

  // - 'Industry and production' merged with 'Economics'.
  // Industry and production URI:
  // http://joinup.eu/ontology/topic/category#industry-and-production
  // Economics URI: http://joinup.eu/ontology/topic/category#economics
  // Move the children of 'Industry and production' to 'Economics'.
  // Delete 'Industry and production'.
  // Create a redirect from 'Industry and production' to 'Economics'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic/category#industry-and-production');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic/category#economics');
  $move_topic_children_to_new_parent('http://joinup.eu/ontology/topic/category#industry-and-production', 'http://joinup.eu/ontology/topic/category#economics');
  $delete_topic_term('http://joinup.eu/ontology/topic/category#industry-and-production');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Information' merged with 'ICT'.
  // Information URI: http://joinup.eu/ontology/topic/category#information
  // ICT URI: http://joinup.eu/ontology/topic/category#ict
  // Move the children of 'Information' to 'ICT'.
  // Delete 'Information'.
  // Create a redirect from 'Information' to 'ICT'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic/category#information');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic/category#ict');
  $move_topic_children_to_new_parent('http://joinup.eu/ontology/topic/category#information', 'http://joinup.eu/ontology/topic/category#ict');
  $delete_topic_term('http://joinup.eu/ontology/topic/category#information');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Law' merged with 'Digital government'.
  // Law URI: http://joinup.eu/ontology/topic/category#law
  // Digital government URI:
  // http://joinup.eu/ontology/topic/category#digital-government
  // Move the children of 'Law' to 'Digital government'.
  // Delete 'Law'.
  // Create a redirect from 'Law' to 'Digital government'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic/category#law');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic/category#digital-government');
  $move_topic_children_to_new_parent('http://joinup.eu/ontology/topic/category#law', 'http://joinup.eu/ontology/topic/category#digital-government');
  $delete_topic_term('http://joinup.eu/ontology/topic/category#law');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Science and technology' Moved to 'Education'. (Why?).
  // Science and technology URI:
  // http://joinup.eu/ontology/topic/category#science-and-technology
  // Education URI: http://joinup.eu/ontology/topic/category#education
  // Move the children of 'Science and technology' to 'Education'.
  // Delete 'Science and technology'.
  // Create a redirect from 'Science and technology' to 'Education'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic/category#science-and-technology');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic/category#education');
  $move_topic_children_to_new_parent('http://joinup.eu/ontology/topic/category#science-and-technology', 'http://joinup.eu/ontology/topic/category#education');
  $delete_topic_term('http://joinup.eu/ontology/topic/category#science-and-technology');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Social' moved to 'Digital government'.
  // Social URI: http://joinup.eu/ontology/topic/category#social
  // Digital government URI:
  // http://joinup.eu/ontology/topic/category#digital-government
  // Move the children of 'Social' to 'Digital government'.
  // Delete 'Social'.
  // Create a redirect from 'Social' to 'Digital government'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic/category#social');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic/category#digital-government');
  $move_topic_children_to_new_parent('http://joinup.eu/ontology/topic/category#social', 'http://joinup.eu/ontology/topic/category#digital-government');
  $delete_topic_term('http://joinup.eu/ontology/topic/category#social');
  $create_redirect($old_internal_path, $new_internal_path);

  // *** Changes to the child terms ***
  // - 'Business and competition' moved to 'Digital government'.
  // Business and competition URI:
  // http://joinup.eu/ontology/topic#business-and-competition
  // Moved to Digital government URI:
  // http://joinup.eu/ontology/topic/category#digital-government
  // Change the parent of 'Business and competition' to 'Digital government'.
  // No need for further changes.
  $change_topic_parent('http://joinup.eu/ontology/topic#business-and-competition', 'http://joinup.eu/ontology/topic/category#digital-government');

  // - 'Defence' merged with 'ICT Security'.
  // Defence URI: http://joinup.eu/ontology/topic#defence
  // ICT Security URI: http://joinup.eu/ontology/topic#ict-security
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Delete 'Defence'.
  // Create a redirect from 'Defence' to 'ICT Security'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#defence');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#ict-security');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#defence', 'http://joinup.eu/ontology/topic#ict-security');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#defence', 'http://joinup.eu/ontology/topic#ict-security');
  $delete_topic_term('http://joinup.eu/ontology/topic#defence');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Economic analysis' merged with 'Banking and financial services'.
  // Economic analysis URI: http://joinup.eu/ontology/topic#economic-analysis
  // Banking and financial services URI:
  // http://joinup.eu/ontology/topic#banking-and-financial-services
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Delete 'Economic analysis'.
  // Create a redirect from 'Economic analysis' to 'Banking and financial
  // services'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#economic-analysis');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#banking-and-financial-services');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#economic-analysis', 'http://joinup.eu/ontology/topic#banking-and-financial-services');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#economic-analysis', 'http://joinup.eu/ontology/topic#banking-and-financial-services');
  $delete_topic_term('http://joinup.eu/ontology/topic#economic-analysis');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'EU finance' merged with 'Banking and financial services'.
  // EU finance URI: http://joinup.eu/ontology/topic#eu-finance
  // Banking and financial services URI:
  // http://joinup.eu/ontology/topic#banking-and-financial-services
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Delete 'EU finance'.
  // Create a redirect from 'EU finance' to 'Banking and financial services'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#eu-finance');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#banking-and-financial-services');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#eu-finance', 'http://joinup.eu/ontology/topic#banking-and-financial-services');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#eu-finance', 'http://joinup.eu/ontology/topic#banking-and-financial-services');
  $delete_topic_term('http://joinup.eu/ontology/topic#eu-finance');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Free and open source software in public Administrations' moved to 'ICT'.
  // Shortened name and merged with 'Free and open source software'.
  // 'Free and open source software in Public Administrations' URI:
  // http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations
  // ICT category URI: http://joinup.eu/ontology/topic/category#ict
  // 'Free and open source software' new URI:
  // http://joinup.eu/ontology/topic#free-and-open-source-software
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Redirects to be created.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations');
  $rename_topic('Free and open source software in Public Administrations', 'Free and open source software');
  $change_topic_parent('http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations', 'http://joinup.eu/ontology/topic/category#ict');
  $change_topic_id('http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations', 'http://joinup.eu/ontology/topic#free-and-open-source-software');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#free-and-open-source-software');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations', 'http://joinup.eu/ontology/topic#free-and-open-source-software');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations', 'http://joinup.eu/ontology/topic#free-and-open-source-software');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'ICT standardisation in eGovernment' renamed.
  // Rename to 'ICT standardisation'.
  // ICT standardisation in eGovernment URI:
  // http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment
  // Change topic ID to: http://joinup.eu/ontology/topic#ict-standardisation
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Create a redirect from 'ICT standardisation in eGovernment' to 'ICT
  // standardisation'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment');
  $rename_topic('ICT standardisation in eGovernment', 'ICT standardisation');
  $change_topic_id('http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment', 'http://joinup.eu/ontology/topic#ict-standardisation');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#ict-standardisation');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment', 'http://joinup.eu/ontology/topic#ict-standardisation');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment', 'http://joinup.eu/ontology/topic#ict-standardisation');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Open government' merged with 'eGovernment'.
  // Open government URI: http://joinup.eu/ontology/topic#open-government
  // eGovernment URI: http://joinup.eu/ontology/topic/category#egovernment
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Delete 'Open government'.
  // Create a redirect from 'Open government' to 'eGovernment'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#open-government');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#egovernment');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#open-government', 'http://joinup.eu/ontology/topic#egovernment');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#open-government', 'http://joinup.eu/ontology/topic#egovernment');
  $delete_topic_term('http://joinup.eu/ontology/topic#open-government');
  $create_redirect($old_internal_path, $new_internal_path);

  // Create a new "Energy and environment" topic in the "Energy and environment"
  // category.
  $create_topic_term('Energy and environment', 'http://joinup.eu/ontology/topic/category#energy-and-environment');

  // - 'Energy' merged with 'Energy and environment'.
  // Energy URI: http://joinup.eu/ontology/topic#energy
  // Energy and environment URI:
  // http://joinup.eu/ontology/topic#energy-and-environment
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Delete 'Energy'.
  // Create a redirect from 'Energy' to 'Energy and environment'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#energy');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#energy-and-environment');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#energy', 'http://joinup.eu/ontology/topic#energy-and-environment');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#energy', 'http://joinup.eu/ontology/topic#energy-and-environment');
  $delete_topic_term('http://joinup.eu/ontology/topic#energy');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Environment' merged with 'Energy and environment'.
  // Environment URI: http://joinup.eu/ontology/topic#environment
  // Energy and environment URI:
  // http://joinup.eu/ontology/topic#energy-and-environment
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Delete 'Environment'.
  // Create a redirect from 'Environment' to 'Energy and environment'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#environment');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#energy-and-environment');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#environment', 'http://joinup.eu/ontology/topic#energy-and-environment');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#environment', 'http://joinup.eu/ontology/topic#energy-and-environment');
  $delete_topic_term('http://joinup.eu/ontology/topic#environment');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Authorisation and authentication' merged with 'eIdentity and
  // eSignature'.
  // Authorisation and authentication URI:
  // http://joinup.eu/ontology/topic#authorisation-and-authentication
  // eIdentity and eSignature URI:
  // http://joinup.eu/ontology/topic#eidentity-and-esignature
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Delete 'Authorisation and authentication'.
  // Create a redirect from 'Authorisation and authentication' to 'eIdentity
  // and eSignature'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#authorisation-and-authentication');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#eidentity-and-esignature');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#authorisation-and-authentication', 'http://joinup.eu/ontology/topic#eidentity-and-esignature');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#authorisation-and-authentication', 'http://joinup.eu/ontology/topic#eidentity-and-esignature');
  $delete_topic_term('http://joinup.eu/ontology/topic#authorisation-and-authentication');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Industry and production' moved to 'Economics'.
  // Industry and production URI:
  // http://joinup.eu/ontology/topic#industry-and-production
  // Economics URI: http://joinup.eu/ontology/topic#economics
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Create a redirect from 'Industry and production' to 'Economics'.
  $change_topic_parent('http://joinup.eu/ontology/topic#industry-and-production', 'http://joinup.eu/ontology/category#economics');

  // - 'Content and knowledge management' moved to 'ICT' category.
  // Content and knowledge management URI:
  // http://joinup.eu/ontology/topic#content-and-knowledge-management
  // ICT Category URI: http://joinup.eu/ontology/category#ict
  $change_topic_parent('http://joinup.eu/ontology/topic#content-and-knowledge-management', 'http://joinup.eu/ontology/category#ict');

  // - 'Information' merged with 'Content and knowledge management'.
  // Information URI: http://joinup.eu/ontology/topic#information
  // Content and knowledge management URI:
  // http://joinup.eu/ontology/topic#content-and-knowledge-management
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Delete 'Information'.
  // Create a redirect from 'Information' to 'Content and knowledge management'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#information');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#content-and-knowledge-management');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#information', 'http://joinup.eu/ontology/topic#content-and-knowledge-management');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#information', 'http://joinup.eu/ontology/topic#content-and-knowledge-management');
  $delete_topic_term('http://joinup.eu/ontology/topic#information');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Statistics' moved to 'ICT'.
  // Statistics URI: http://joinup.eu/ontology/topic#statistics
  // Moved to ICT URI: http://joinup.eu/ontology/topic/category#ict
  $change_topic_parent('http://joinup.eu/ontology/topic#statistics', 'http://joinup.eu/ontology/category#ict');

  // - 'eJustice' moved to 'Digital government'.
  // eJustice URI: http://joinup.eu/ontology/topic#ejustice
  // Moved to Digital government URI:
  // http://joinup.eu/ontology/topic/category#digital-government
  $change_topic_parent('http://joinup.eu/ontology/topic#ejustice', 'http://joinup.eu/ontology/category#digital-government');

  // - 'European Policies' moved to 'Digital government'.
  // European Policies URI: http://joinup.eu/ontology/topic#european-policies
  // Moved to Digital government URI:
  // http://joinup.eu/ontology/topic/category#digital-government
  $change_topic_parent('http://joinup.eu/ontology/topic#european-policies', 'http://joinup.eu/ontology/category#digital-government');

  // - 'Law' moved to 'Digital government'.
  // Law URI: http://joinup.eu/ontology/topic#law
  // Moved to Digital government URI:
  // http://joinup.eu/ontology/topic/category#digital-government
  $change_topic_parent('http://joinup.eu/ontology/topic#law', 'http://joinup.eu/ontology/category#digital-government');

  // - 'Security' moved to 'ICT'.
  // Security URI: http://joinup.eu/ontology/topic#security
  // Moved to ICT URI: http://joinup.eu/ontology/topic/category#ict
  $change_topic_parent('http://joinup.eu/ontology/topic#security', 'http://joinup.eu/ontology/category#ict');

  // - 'Security' merged with 'ICT Security'.
  // Security URI: http://joinup.eu/ontology/topic#security
  // ICT Security URI: http://joinup.eu/ontology/topic#ict-security
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Delete 'Security'.
  // Create a redirect from 'Security' to 'ICT Security'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#security');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#ict-security');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#security', 'http://joinup.eu/ontology/topic#ict-security');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#security', 'http://joinup.eu/ontology/topic#ict-security');
  $delete_topic_term('http://joinup.eu/ontology/topic#security');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'eParticipation and eVoting' moved to 'Education'.
  // eParticipation and eVoting URI:
  // http://joinup.eu/ontology/topic#eparticipation-and-evoting
  // Moved to Education URI: http://joinup.eu/ontology/topic/category#education
  $change_topic_parent('http://joinup.eu/ontology/topic#eparticipation-and-evoting', 'http://joinup.eu/ontology/category#education');

  // - 'Politics' merged with 'European policies'.
  // Politics URI: http://joinup.eu/ontology/topic#politics
  // European policies URI: http://joinup.eu/ontology/topic#european-policies
  // Update the SPARQL entity references.
  // Update the MySQL entity references.
  // Delete 'Politics'.
  // Create a redirect from 'Politics' to 'European policies'.
  $old_internal_path = $get_path('http://joinup.eu/ontology/topic#politics');
  $new_internal_path = $get_path('http://joinup.eu/ontology/topic#european-policies');
  $update_sparql_entity_references('http://joinup.eu/ontology/topic#politics', 'http://joinup.eu/ontology/topic#european-policies');
  $update_mysql_entity_references('http://joinup.eu/ontology/topic#politics', 'http://joinup.eu/ontology/topic#european-policies');
  $delete_topic_term('http://joinup.eu/ontology/topic#politics');
  $create_redirect($old_internal_path, $new_internal_path);

  // - 'Science and technology' moved to 'Education'.
  // Science and technology URI:
  // http://joinup.eu/ontology/topic#science-and-technology
  // Moved to Education URI: http://joinup.eu/ontology/topic/category#education
  $change_topic_parent('http://joinup.eu/ontology/topic#science-and-technology', 'http://joinup.eu/ontology/category#education');

  // - 'Demography and population' moved to 'Digital government'.
  // Demography and population URI:
  // http://joinup.eu/ontology/topic#demography-and-population
  // Moved to Digital government URI:
  // http://joinup.eu/ontology/topic/category#digital-government
  $change_topic_parent('http://joinup.eu/ontology/topic#demography-and-population', 'http://joinup.eu/ontology/category#digital-government');

  // - 'eInclusion' moved to 'Digital government'.
  // eInclusion URI: http://joinup.eu/ontology/topic#einclusion
  // Moved to Digital government URI:
  // http://joinup.eu/ontology/topic/category#digital-government
  $change_topic_parent('http://joinup.eu/ontology/topic#einclusion', 'http://joinup.eu/ontology/category#digital-government');

  // - 'Social' moved to 'Digital government'.
  // Social URI: http://joinup.eu/ontology/topic#social
  // Moved to Digital government URI:
  // http://joinup.eu/ontology/topic/category#digital-government
  $change_topic_parent('http://joinup.eu/ontology/topic#social', 'http://joinup.eu/ontology/category#digital-government');

  // - 'Smart cities' new topic in 'Digital government'.
  // Smart cities URI: http://joinup.eu/ontology/topic#smart-cities
  $create_topic_term('http://joinup.eu/ontology/topic#smart-cities', 'http://joinup.eu/ontology/category#digital-government');

  return "All topics have been updated";
}
