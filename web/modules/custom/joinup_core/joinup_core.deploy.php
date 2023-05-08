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

use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Fix glossary link for solution entities.
 */
function joinup_core_deploy_109200(array &$sandbox = []): string {
  /** @var \Drupal\sparql_entity_storage\SparqlEntityStorageInterface $solutionStorage */
  $solutionStorage = \Drupal::entityTypeManager()
    ->getStorage('rdf_entity');

  /** @var \Drupal\menu_link_content\MenuLinkContentStorageInterface $menuLinkContentStorage */
  $menuLinkContentStorage = \Drupal::entityTypeManager()
    ->getStorage('menu_link_content');

  if (empty($sandbox['ids'])) {
    $solutionIds = $solutionStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('rid', 'solution')
      ->execute();

    $sandbox['ids'] = array_values($solutionIds);
    $sandbox['max'] = count($sandbox['ids']);
    $sandbox['current'] = 0;
  }

  $ids = array_splice($sandbox['ids'], 0, 100);

  /** @var \Drupal\solution\Entity\SolutionInterface $solution */
  foreach ($solutionStorage->loadMultiple($ids) as $solution) {
    $menuIds = $menuLinkContentStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', 'ogmenu-', 'STARTS_WITH')
      ->condition('title', 'Glossary')
      ->condition('link.uri', 'route:entity.rdf_entity.glossary', 'STARTS_WITH')
      ->condition('link.uri', UriEncoder::encodeUrl($solution->id()), 'ENDS_WITH')
      ->execute();

    if (empty($menuIds)) {
      continue;
    }

    /** @var Drupal\menu_link_content\Entity\MenuLinkContent $menuLinkContent */
    $menuLinkContent = $menuLinkContentStorage->load(reset($menuIds));
    if (!$menuLinkContent) {
      continue;
    }

    $menuLinkContent->link->uri = str_replace(
      UriEncoder::encodeUrl($solution->id()),
      UriEncoder::encodeUrl($solution->getCollection()->id()),
      $menuLinkContent->link->uri
    );
    $menuLinkContent->save();
  }

  $sandbox['current'] += count($ids);
  $sandbox['#finished'] = (int) empty($sandbox['ids']);

  return "Updated {$sandbox['current']} of {$sandbox['max']} glossary menu links.";
}

/**
 * Adding blocked users before the spam activity (03/12/2022) to queue.
 */
function joinup_core_deploy_109201(): string {
  $from_date = new \DateTime("2022/12/03");
  $users_ids = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->condition('status', '0')
    ->condition('uid', 1, '>')
    ->condition('created', $from_date->getTimestamp(), '<=')
    ->execute();

  $count = count($users_ids);
  $queue = \Drupal::queue('joinup_removed_users');
  $queue->createQueue();

  while (!empty($users_ids)) {
    $ids = array_splice($users_ids, 0, 10);
    $queue->createItem($ids);
  }

  return "Add $count users to queue.";
}

/**
 * Clears the entity definition cache.
 */
function joinup_core_deploy_109202(): string {
  $entityTypeManager = \Drupal::entityTypeManager();
  $entityTypeManager->clearCachedDefinitions();

  // Install the field_discussion_logo field to the discussion node bundle.
  try {
    $fieldStorage = $entityTypeManager->getStorage('field_storage_config')->load('node.field_discussion_logo');
    $fieldStorage->setTranslatable(TRUE);
    $fieldStorage->save();
  }
  catch (\Exception $e) {
    return 'Could not install discussion logo field storage.';
  }

  try {
    $field = $entityTypeManager->getStorage('field_config')->load('node.discussion.field_discussion_logo');
    $field->setTranslatable(TRUE);
    $field->save();
  }
  catch (\Exception $e) {
    return 'Could not install discussion logo field instance.';
  }

  return 'Entity definition cache cleared. Discussion logo installed.';
}
