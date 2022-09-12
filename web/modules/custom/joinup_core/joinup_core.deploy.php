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
 * Implements hook_update().
 *
 * Create a Link to EIF perspective page from the solution's left-side menu.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7071
 */
function joinup_core_deploy_0108502(&$sandbox = NULL) {
  $solutions_ids = \Drupal::entityQuery('rdf_entity')
    ->condition('rid', 'solution')
    ->execute();
  $solutions = \Drupal::entityTypeManager()->getStorage('rdf_entity')->loadMultiple($solutions_ids);
  foreach ($solutions as $solution) {
    if ($solution->eif_perspective->entity) {
      $ogMenus = \Drupal::entityTypeManager()->getStorage('ogmenu_instance')->loadByProperties([
        'og_audience' => $solution->id(),
      ]);
      if ($ogMenu = reset($ogMenus)) {
        $menuName = 'ogmenu-' . $ogMenu->id();
        $menuLinkContentStorage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
        $link = ['uri' => $solution->toUrl('eif-perspective')->toUriString()];
        $menuLinkContentStorage->create([
          'title' => t('EIF view'),
          'menu_name' => $menuName,
          'link' => $link,
          'weight' => -6,
        ])->save();
      }
    }
  }
}
