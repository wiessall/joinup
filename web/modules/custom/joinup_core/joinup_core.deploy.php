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

use Drupal\joinup_group\Event\GroupMenuLinksEvent;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og_menu\Entity\OgMenuInstance;

/**
 * Create a quick links menu for the groups.
 */
function joinup_core_deploy_110100(array &$sandbox): string {
  if (empty($sandbox['group_ids'])) {
    $groups = \Drupal::entityTypeManager()->getStorage('rdf_entity')->getQuery()
      ->accessCheck(FALSE)
      ->condition('rid', ['collection', 'solution'], 'IN')
      ->execute();
    $sandbox['group_ids'] = array_keys($groups);
    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['group_ids']);
  }

  $group_ids = array_splice($sandbox['group_ids'], 0, 50);
  $groups = \Drupal::getContainer()->get('entity_type.manager')->getStorage('rdf_entity')->loadMultiple($group_ids);
  foreach ($groups as $group) {
    $og_menu_instance = OgMenuInstance::create([
      'type' => 'quick_links',
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $group->id(),
    ]);
    $og_menu_instance->save();
    $menu_name = 'ogmenu-' . $og_menu_instance->id();
    $event_dispatcher = \Drupal::getContainer()->get('event_dispatcher');

    // Allow subscribers to automatically create the menu links.
    $event = new GroupMenuLinksEvent($group, $menu_name, $og_menu_instance->bundle(), \Drupal::entityTypeManager());
    $event_dispatcher->dispatch($event);
  }

  $sandbox['progress'] += count($group_ids);
  $sandbox['#finished'] = (int) empty($sandbox['group_ids']);
  return "Processed {$sandbox['progress']} out of {$sandbox['count']} groups.";
}
