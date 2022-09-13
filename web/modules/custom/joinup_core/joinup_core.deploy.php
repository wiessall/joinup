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

use Drupal\solution\Entity\SolutionInterface;

/**
 * Implements hook_deploy().
 *
 * Create a Link to EIF perspective view from the solution's left-side menu.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7071
 */
function joinup_core_deploy_0108502() {
  $eif_perspective_ids = \Drupal::entityQuery('rdf_meta_entity')
    ->condition('type', 'eif_perspective')
    ->execute();
  $eif_perspectives = \Drupal::entityTypeManager()->getStorage('rdf_meta_entity')->loadMultiple($eif_perspective_ids);
  foreach ($eif_perspectives as $eif_perspective) {
    $rdf_entity = $eif_perspective->getTargetEntity();
    if ($rdf_entity instanceof SolutionInterface) {
      $og_menus = \Drupal::entityTypeManager()->getStorage('ogmenu_instance')->loadByProperties([
        'og_audience' => $rdf_entity->id(),
      ]);
      if ($og_menu = reset($og_menus)) {
        $menu_name = 'ogmenu-' . $og_menu->id();
        $menu_link_content_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
        $link = ['uri' => $rdf_entity->toUrl('eif-perspective')->toUriString()];
        $menu_link_content_storage->create([
          'title' => t('EIF view'),
          'menu_name' => $menu_name,
          'link' => $link,
          'weight' => -6,
        ])->save();
      }
    }
  }
}
