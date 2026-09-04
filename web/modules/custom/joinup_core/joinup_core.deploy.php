<?php

/**
 * @file
 * Deploy hooks for the Joinup core module.
 */

declare(strict_types=1);

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Database\Database;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\joinup_core\Update\NodeTranslationsRollback;
use Drupal\joinup_core\Update\RevisionTranslationAffectedRepair;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\node\Entity\Node;

/**
 * Converts absolute internal menu link URLs into internal paths.
 */
function joinup_core_deploy_201600(array &$sandbox): string {
  if (!isset($sandbox['uris'])) {
    $query = \Drupal::database()->select('menu_link_content_data', 'md');
    $query->fields('md', ['id', 'link__uri']);
    // The 'link' field is not translatable, so the default translation carries
    // the only value that needs to be inspected.
    $query->condition('md.default_langcode', 1);
    $query->condition('md.link__uri', 'http%', 'LIKE');

    // The converted URI is computed once, here, and kept for the processing
    // phase, keyed by menu link ID. Links that need no conversion are dropped
    // straight away rather than being loaded and inspected a second time.
    $sandbox['uris'] = [];
    foreach ($query->execute() as $row) {
      $internalUri = _joinup_core_get_internal_uri($row->link__uri);
      if ($internalUri !== NULL) {
        $sandbox['uris'][(int) $row->id] = $internalUri;
      }
    }
    $sandbox['total'] = count($sandbox['uris']);
    $sandbox['progress'] = 0;
  }

  if (!$sandbox['uris']) {
    $sandbox['#finished'] = 1;
    return 'No menu links with an absolute internal URL were found.';
  }

  // Taking the key and unsetting it avoids the array_shift() reindexing of the
  // whole remaining list on every iteration.
  $id = array_key_first($sandbox['uris']);
  $internalUri = $sandbox['uris'][$id];
  unset($sandbox['uris'][$id]);
  $sandbox['progress']++;
  $sandbox['#finished'] = (int) empty($sandbox['uris']);

  /** @var \Drupal\menu_link_content\MenuLinkContentInterface|null $link */
  $link = \Drupal::entityTypeManager()->getStorage('menu_link_content')->load($id);

  if (!$link instanceof MenuLinkContentInterface) {
    return sprintf(
      'Skipped menu link %d, it no longer exists, %d out of %d.',
      $id,
      $sandbox['progress'],
      $sandbox['total']
    );
  }

  $uri = $link->link->uri;
  $link->link->uri = $internalUri;
  // Let's not create unnecessary revisions.
  $link->setNewRevision(FALSE);
  $link->save();

  return sprintf(
    "Converted menu link %d from '%s' to '%s', %d out of %d.",
    $id,
    $uri,
    $internalUri,
    $sandbox['progress'],
    $sandbox['total']
  );
}

/**
 * Returns the internal URI equivalent of an absolute URL pointing at Joinup.
 *
 * This is mainly a copy of
 * \Drupal\menu_link_content\Form\MenuLinkContentForm::reformatInternalLinkIfEnteredAsExternal
 * from the patch.
 *
 * @param string $uri
 *   The URI stored in the menu link.
 *
 * @return string|null
 *   The equivalent 'internal:' URI, or NULL if the URI is not an absolute URL
 *   pointing at Joinup.
 */
function _joinup_core_get_internal_uri(string $uri): ?string {
  $request = \Drupal::request();
  $hosts = array_map('strtolower', [
    $request->getHost(),
    'interoperable-europe.ec.europa.eu',
    'joinup.ec.europa.eu',
  ]);

  // Schemes such as mailto: and tel: are external but carry no host.
  if (!UrlHelper::isExternal($uri)) {
    return NULL;
  }
  $parts = parse_url($uri);
  if (empty($parts['host']) || !in_array(strtolower($parts['host']), $hosts, TRUE)) {
    return NULL;
  }

  $path = $parts['path'] ?? '/';
  // Drop the base path, in case the portal is served from a subdirectory.
  $basePath = $request->getBasePath();
  if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
    $path = substr($path, strlen($basePath));
  }

  // Drop the language prefix, if any.
  $prefixes = array_filter(\Drupal::config('language.negotiation')->get('url.prefixes') ?? []);
  $segments = explode('/', ltrim($path, '/'));
  if (in_array($segments[0], $prefixes, TRUE)) {
    array_shift($segments);
  }

  return 'internal:/' . implode('/', $segments)
    . (isset($parts['query']) ? '?' . $parts['query'] : '')
    . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

/**
 * Clears revision_translation_affected where no translation was affected.
 */
function joinup_core_deploy_201601(array &$sandbox): string {
  return \Drupal::classResolver(RevisionTranslationAffectedRepair::class)->run($sandbox);
}

/**
 * Rolls back nodes that lost paragraph references during translation saves.
 */
function joinup_core_deploy_201602(): string {
  return \Drupal::classResolver(NodeTranslationsRollback::class)->run();
}

/**
 * Queues nodes with more than 10 revisions in a language for revision delete.
 */
function joinup_core_deploy_201603(): string {
  $threshold = 10;

  $connection = Database::getConnection();
  $query = $connection->select('node_field_revision', 'nfr');
  $query->addField('nfr', 'nid');
  $query->addExpression('COUNT(nfr.vid)', 'revision_count');
  $query->condition('nfr.revision_translation_affected', 1);
  $query->groupBy('nfr.nid');
  $query->groupBy('nfr.langcode');
  $query->havingCondition('revision_count', $threshold, '>');
  $nids = array_unique($query->execute()->fetchCol());

  if (!$nids) {
    return sprintf('No nodes found with more than %d revisions in a language.', $threshold);
  }

  /** @var \Drupal\node_revision_delete\NodeRevisionDeleteInterface $node_revision_delete */
  $node_revision_delete = \Drupal::service('node_revision_delete');

  $queued = 0;
  foreach ($nids as $nid) {
    if ($node_revision_delete->createQueueItem((int) $nid)->queued()) {
      $queued++;
    }
  }

  return sprintf('Queued %d out of %d node(s) with more than %d revisions in a language for revision deletion.', $queued, count($nids), $threshold);
}

/**
 * Changes eif_description format from basic_html to content_editor.
 */
function joinup_core_deploy_201604(): string {
  $database = \Drupal::database();
  $updated = $database->update('meta_entity__eif_description')
    ->fields([
      'eif_description_format' => 'content_editor',
    ])
    ->condition('eif_description_format', 'basic_html')
    ->execute();

  return sprintf('Updated %d eif_description format value(s) from "basic_html" to "content_editor".', $updated);
}

/**
 * Adjust menu links of SEMIC Conference collection.
 */
function joinup_core_deploy_201605(): void {
  // Remove parent of the Overview link.
  $menuTreeStorage = \Drupal::service(MenuTreeStorageInterface::class);
  $menuLinkManager = \Drupal::service(MenuLinkManagerInterface::class);
  $semicConferenceHome = $menuTreeStorage->load('group_link:node-734388-navigation');
  $menuLinkManager->updateDefinition('group_link:overview-730634-navigation', [
    'parent' => '',
    'enabled' => 1,
    'weight' => $semicConferenceHome['weight'],
  ], FALSE);

  // Move children of the custom landing page's menu link under the overview
  // link.
  foreach ($menuTreeStorage->loadAllChildren('group_link:node-734388-navigation') as $definition) {
    $menuLinkManager->updateDefinition($definition['id'], [
      'parent' => 'group_link:overview-730634-navigation',
    ], FALSE);
  }

  // Disable menu link of the custom landing page.
  $menuLinkManager->updateDefinition('group_link:node-734388-navigation', [
    'enabled' => 0,
  ], FALSE);

  // Change "Overview" label to "Home".
  $group = Node::load(730634);
  assert($group instanceof GroupInterface);
  $settings = MetaEntity::loadOrCreate('collection_settings', $group);
  $settings->set('overview_label', 'Home')->save();
}
