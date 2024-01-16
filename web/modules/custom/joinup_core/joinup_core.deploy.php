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
use Drupal\solution\Entity\SolutionInterface;

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
  $event_dispatcher = \Drupal::getContainer()->get('event_dispatcher');
  $rdf_repository = \Drupal::getContainer()->get('rdf_meta_entity.repository');
  foreach ($groups as $group) {
    $og_menu_instance = OgMenuInstance::create([
      'type' => 'quick_links',
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $group->id(),
    ]);
    $og_menu_instance->save();
    $menu_name = 'ogmenu-' . $og_menu_instance->id();

    // Allow subscribers to automatically create the menu links.
    $event = new GroupMenuLinksEvent($group, $menu_name, $og_menu_instance->bundle(), \Drupal::entityTypeManager());
    $event_dispatcher->dispatch($event);

    if (!$group instanceof SolutionInterface) {
      continue;
    }

    if ($eif_perspective = $rdf_repository->getMetaEntityForEntity($group, 'eif_perspective')) {
      _eif_add_eif_link_to_group_menus($eif_perspective);
    }
  }

  $sandbox['progress'] += count($group_ids);
  $sandbox['#finished'] = (int) empty($sandbox['group_ids']);
  return "Processed {$sandbox['progress']} out of {$sandbox['count']} groups.";
}
