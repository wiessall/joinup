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

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Utility\UpdateException;
use Drupal\joinup_group\Event\GroupMenuLinksEvent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_link_content\MenuLinkContentStorageInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og_menu\Entity\OgMenuInstance;
use Drupal\og_menu\OgMenuInstanceInterface;
use Drupal\smart_trim\TruncateHTML;

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

/**
 * Alter abstracts of collections.
 */
function joinup_core_deploy_110101(array &$sandbox): string {
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
 * Move custom page links from OG navigation to OG quick links menu.
 */
function joinup_core_deploy_110106(array &$sandbox): string {
  $menuLinkContentStorage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  \assert($menuLinkContentStorage instanceof MenuLinkContentStorageInterface);
  if ($menuLinkContentStorage->getMenuLinkIdsWithPendingRevisions()) {
    throw new UpdateException('Menu links with pending revisions exist.');
  }

  // Initialize: Get the IDs of custom pages and get the menu link content IDs
  // which menu is an OG menu and which might link to a node.
  if (empty($sandbox)) {
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    \assert($nodeStorage instanceof NodeStorageInterface);
    // Get the node IDs of custom page nodes belonging to any group, regardless
    // of their status.
    $sandbox['group_custom_page_nids'] = array_values(
      $nodeStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'custom_page')
        ->exists(OgGroupAudienceHelperInterface::DEFAULT_FIELD)
        ->execute()
    );
    // Get menu links belonging to menus with ID "ogmenu-" and pointing to a
    // node, to an internal URI or to a route.
    $menuLinkQuery = $menuLinkContentStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', 'ogmenu-%', 'LIKE');
    $orGroup = $menuLinkQuery->orConditionGroup()
      ->condition('link__uri', 'entity:node%', 'LIKE')
      ->condition('link__uri', 'internal:%', 'LIKE')
      ->condition('link__uri', 'route:entity.node.canonical%', 'LIKE');
    $sandbox['menu_link_ids_to_process'] = array_values(
      $menuLinkQuery->condition($orGroup)->execute()
    );
    $sandbox['total'] = count($sandbox['menu_link_ids_to_process']);
    $sandbox['progress'] = 0;
  }

  $ogMenuInstanceStorage = \Drupal::entityTypeManager()->getStorage('ogmenu_instance');
  \assert($ogMenuInstanceStorage instanceof ContentEntityStorageInterface);

  // Callback to decide whether the menu link entity links to a custom page node
  // or not.
  $menuLinkIsCustomPage = function (MenuLinkContentInterface $menuLinkContent) use ($sandbox): bool {
    $uriRaw = $menuLinkContent->link->first()->getValue()['uri'];
    $nodeId = NULL;
    // Try to not instantiate the URL object if the raw URI allows us to
    // determine the node ID.
    if (str_starts_with($uriRaw, 'entity:node/')) {
      $nodeIdMatches = NULL;
      preg_match('#^entity:node/(\d+)#', $uriRaw, $nodeIdMatches);
      $nodeId = $nodeIdMatches[1] ?? NULL;
    }
    else {
      // Instantiate the URL object and get the node parameter.
      $uri = $menuLinkContent->getUrlObject();
      if ($uri->isRouted() && $uri->getRouteName() === 'entity.node.canonical') {
        $nodeId = $uri->getRouteParameters()['node'] ?? NULL;
      }
    }

    // Using equals comparison since IDs might be either strings or integers.
    return $nodeId && in_array($nodeIdMatches[1], $sandbox['group_custom_page_nids']);
  };

  $menuLinkContentIdsToProcess = array_splice($sandbox['menu_link_ids_to_process'], 0, 250);
  foreach ($menuLinkContentIdsToProcess as $menuLinkContentId) {
    $menuLinkContent = $menuLinkContentStorage->load($menuLinkContentId);
    \assert($menuLinkContent instanceof MenuLinkContentInterface);
    // Continue if link doesn't refers a custom page node.
    if (!$menuLinkIsCustomPage($menuLinkContent)) {
      continue;
    }

    $originalMenuId = $menuLinkContent->getMenuName();
    $navMenuIdMatches = NULL;
    if (!preg_match('/^ogmenu-(\d+)$/', $originalMenuId, $navMenuIdMatches)) {
      throw new UpdateException("Cannot extract OG navigation menu instance ID from menu ID '$originalMenuId'!");
    }

    $navMenuInstanceId = $navMenuIdMatches[1];
    $navMenuInstance = $ogMenuInstanceStorage->load($navMenuInstanceId);
    if (!$navMenuInstance instanceof OgMenuInstanceInterface) {
      throw new UpdateException("Cannot load OG navigation menu instance from menu ID '$originalMenuId'!");
    }
    if ($navMenuInstance->getType() === 'quick_links') {
      // Already in quick links OG menu, no need to change anything.
      continue;
    }

    $groupId = $navMenuInstance->get(OgGroupAudienceHelperInterface::DEFAULT_FIELD)->getValue()[0]['target_id'] ?? NULL;
    if (!$groupId) {
      throw new UpdateException("OG navigation menu instance with ID '$navMenuInstanceId' does not have a group ID!");
    }
    $quickLinksMenuInstanceIds = $ogMenuInstanceStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'quick_links')
      ->condition(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $groupId)
      ->execute();
    if (!$quickLinksMenuInstanceIds) {
      throw new UpdateException("OG quick links menu instance of group '$groupId' is missing!");
    }
    $quickLinksMenuInstanceId = reset($quickLinksMenuInstanceIds);
    $menuLinkContent
      ->createDuplicate()
      ->set('menu_name', "ogmenu-$quickLinksMenuInstanceId")
      ->save();
  }

  $sandbox['progress'] += count($menuLinkContentIdsToProcess);

  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Processed {$sandbox['progress']} out of {$sandbox['total']}.";
}
