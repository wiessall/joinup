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

use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;

/**
 * Update path alias for EU OSS catalogue.
 */
function joinup_core_deploy_109700(): void {
  /** @var \Drupal\collection\Entity\CollectionInterface $collection */
  $collection = \Drupal::entityTypeManager()->getStorage('rdf_entity')
    ->load(OssCatalogueCollectionInterface::COLLECTION_ENTITY_ID);

  $collection
    ?->set('field_ar_state', 'validated')
      ->set('path', ['alias' => '/eu-oss-catalogue', 'pathauto' => 0])
      ->save();
}

/**
 * Update title of EU OSS catalogue collection.
 */
function joinup_core_deploy_109701(): void {
  /** @var \Drupal\collection\Entity\CollectionInterface $collection */
  $collection = \Drupal::entityTypeManager()->getStorage('rdf_entity')
    ->load(OssCatalogueCollectionInterface::COLLECTION_ENTITY_ID);

  $collection
    ?->set('label', 'EU Open Source Solutions Catalogue')
      ->save();
}

/**
 * Import content for EU OpenSource Software Solutions Catalogue.
 */
function joinup_core_deploy_109702(): void {
  /** @var \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller */
  $moduleInstaller = \Drupal::service('module_installer');

  // Import content.
  $moduleInstaller->install(['default_content']);
  /** @var \Drupal\default_content\ImporterInterface $importer */
  \Drupal::service('default_content.importer')
    ->importContent('joinup_oss_catalogue', TRUE);
  $moduleInstaller->uninstall(['default_content']);
}
