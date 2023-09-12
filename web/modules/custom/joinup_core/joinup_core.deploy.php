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
 * Import content for EU OpenSource Software Solutions Catalogue.
 */
function joinup_core_deploy_109701(): void {
  /** @var \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller */
  $moduleInstaller = \Drupal::service('module_installer');
  /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository */
  $entityRepository = \Drupal::service('entity.repository');

  // Import content.
  $moduleInstaller->install(['default_content']);
  /** @var \Drupal\default_content\ImporterInterface $importer */
  \Drupal::service('default_content.importer')
    ->importContent('joinup_oss_catalogue', TRUE);
  $moduleInstaller->uninstall(['default_content']);

  // Disable auto-created left-side menu link.
  $landingPage = $entityRepository->loadEntityByUuid('node', '54acbd6f-47f0-4280-ad09-0a07c397318b');
  $linkPlugins = \Drupal::service('plugin.manager.menu.link')
    ->loadLinksByRoute('entity.node.canonical', ['node' => $landingPage->id(),], 'ogmenu-3728');
  /** @var \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $linkPlugin */
  $linkPlugin = reset($linkPlugins);
  $entityRepository
    ->loadEntityByUuid('menu_link_content', $linkPlugin->getDerivativeId())
    ->setUnpublished()
    ->save();
}
