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

use Drupal\file\Entity\File;
use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Add navbar menu links.
 */
function joinup_core_post_update_109900(): string {
  $menuLinkStorage = \Drupal::entityTypeManager()
    ->getStorage('menu_link_content');

  $linksToCreate = [
    [
      'title' => 'Interoperable Europe',
      'uri' => 'entity:rdf_entity/' . UriEncoder::encodeUrl(InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID),
      'weight' => 0,
    ],
    [
      'title' => 'Interoperability Solutions',
      'uri' => 'entity:node/' . JoinupCollectionHelper::INTEROPERABILITY_SOLUTIONS_ENTITY_ID,
      'weight' => 1,
    ],
  ];

  foreach ($linksToCreate as $linkData) {
    $menuLinkStorage->create([
      'title' => $linkData['title'],
      'link' => ['uri' => $linkData['uri']],
      'menu_name' => 'navbar',
      'weight' => $linkData['weight'],
    ])->save();
  }

  return 'Navbar menu links have been created';
}
