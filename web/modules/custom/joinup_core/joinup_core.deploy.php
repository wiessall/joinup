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
use Drupal\smart_trim\TruncateHTML;

/**
 * Alter abstracts of collections.
 */
function joinup_core_deploy_110100(array &$sandbox): string {
  $sparql_connection = \Drupal::getContainer()->get('sparql.endpoint');
  if (empty($sandbox['data'])) {
    $query = <<<SPARQL
SELECT ?graph ?collection ?abstract
WHERE {
    GRAPH ?graph {
        ?collection <http://purl.org/dc/terms/abstract> ?abstract .
    }
    VALUES ?graph {
        <http://joinup.eu/collection/published>
        <http://joinup.eu/collection/draft>
    }
}
SPARQL;

    $results = $sparql_connection->query($query);
    foreach ($results as $result) {
      $sandbox['data'][] = [
        'graph' => $result->graph->getUri(),
        'collection' => $result->collection->getUri(),
        'abstract' => $result->abstract->getValue(),
      ];
    }
    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['data']);
  }

  $truncater = new TruncateHTML();
  $spliced = array_splice($sandbox['data'], 0, 300);
  foreach ($spliced as $item) {
    $item['abstract'] = trim(strip_tags($item['abstract']));
    $characters_to_replace = [
      '"' => '\\"',
      "'" => "\\'",
      "\xc2\xa0" => ' ',
      // The non-breaking space is stored as plain text after the tag stripping.
      "&nbsp;" => ' ',
    ];
    $item['abstract'] = strtr($item['abstract'], $characters_to_replace);
    // Replace multiple spaces with a single space.
    $item['abstract'] = preg_replace('/\s+/', ' ', $item['abstract']);

    // Trim the abstract to 200 characters.
    $item['abstract'] = $truncater->truncateChars($item['abstract'], 200);

    $query = <<<SPARQL
WITH <{$item['graph']}>
DELETE {
    <{$item['collection']}> <http://joinup.eu/text_format> ?format .
    <{$item['collection']}> <http://purl.org/dc/terms/abstract> ?abstract .
}
INSERT {
    <{$item['collection']}> <http://purl.org/dc/terms/abstract> "{$item['abstract']}" .
}
WHERE {
    <{$item['collection']}> <http://purl.org/dc/terms/abstract> ?abstract .
}
SPARQL;
    $sparql_connection->update($query);
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['data']);
  return "Processed {$sandbox['progress']} out of {$sandbox['count']}.";
}

/**
 * Create a quick links menu for the groups.
 */
function joinup_core_deploy_110101(array &$sandbox): string {
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

/**
 * Move EIF perspective menu links to the quick links menu.
 */
function joinup_core_deploy_110102(array &$sandbox): string {
  if (empty($sandbox['group_ids'])) {
    // Load the menu links with title 'EIF Perspective' pointing to a URI that
    // starts with 'route:entity.rdf_entity.eif_perspective;rdf_entity'. It is
    // not likely to be any that does not point to the route, but we check for
    // it anyway.
    $query = \Drupal::entityQuery('menu_link_content')
      ->condition('title', 'EIF Perspective')
      ->condition('link.uri', 'route:entity.rdf_entity.eif_perspective;rdf_entity', 'STARTS_WITH');
    $link_ids = $query->execute();
    $sandbox['link_ids'] = array_keys($link_ids);
  }

  $menu_helper = \Drupal::getContainer()->get('joinup_menu.menu_helper');
  $menu_instance_storage = \Drupal::entityTypeManager()->getStorage('ogmenu_instance');
  $link_ids = array_splice($sandbox['link_ids'], 0, 50);
  $links = \Drupal::getContainer()->get('entity_type.manager')->getStorage('menu_link_content')->loadMultiple($link_ids);
  /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
  foreach ($links as $link) {
    $og_menu_instance_id = substr($link->getMenuName(), strlen('ogmenu-'));
    $og_menu_instance = $menu_instance_storage->load($og_menu_instance_id);
    $group = $og_menu_instance->getGroup();

    // Get the quick links menu for the group.
    $quick_links_menu = $menu_helper->getOgMenuByGroup('quick_links', $group->id());
    if (!$quick_links_menu) {
      continue;
    }

    // Move the link to the quick links menu.
    $link->set('menu_name', $quick_links_menu->id());
    $link->save();
  }

  $sandbox['#finished'] = (int) empty($sandbox['link_ids']);
  return "Processed {$sandbox['progress']} out of {$sandbox['count']} links.";
}
