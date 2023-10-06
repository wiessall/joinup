<?php

/**
 * @file
 * Post update functions of Contact Information.
 *
 * Post update hook descriptions are used as the description of the hook, so the
 * common Drupal practice allows having multi-line doc comment here (and also at
 * update hooks), so we suppress this sniff. Also, we use different update names
 * than what the common standard allows, so we disable InvalidUpdateId as well.
 *
 * @phpcs:disable Drupal.Commenting.DocComment.ShortSingleLine,Drupal.Commenting.DocComment.MissingShort,CodingStandards.NamingConventions.ValidUpdateName.InvalidUpdateId
 */

declare(strict_types = 1);

use Drupal\rdf_entity\RdfInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorageInterface;

/**
 * Re-saves all contact information entity to fix outdated uid value data type
 * (causing JsonLD serialization issues).
 */
function contact_information_post_update_0109900(&$sandbox) {
  $storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
  assert($storage instanceof SparqlEntityStorageInterface);

  if (!isset($sandbox['progress'])) {
    // This must be the first run. Initialize the sandbox.
    $idList = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('rid', 'contact_information')
      ->execute();
    $sandbox['progress'] = 0;
    $sandbox['ids'] = array_values($idList);
    $sandbox['total'] = count($idList);
  }

  $idsToProcess = array_slice($sandbox['ids'], $sandbox['progress'], 50);

  foreach ($storage->loadMultiple($idsToProcess) as $entity) {
    assert($entity instanceof RdfInterface);
    $entity->save();
  }

  $sandbox['progress'] += count($idsToProcess);
  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];

  if ($sandbox['progress'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
    return t('Re-saved @count-total contact information entities in total.', [
      '@count-total' => $sandbox['total'],
    ]);
  }
}
