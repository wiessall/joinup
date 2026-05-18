<?php

/**
 * @file
 * Deploy hooks for the Joinup OSS Catalogue module.
 */

declare(strict_types=1);

/**
 * Delete duplicate node 733778.
 *
 * The node is superseded by
 * /eu-oss-catalogue/solutions/govcy-unified-design-system.
 */
function joinup_oss_catalogue_deploy_201300(): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $node = $storage->load(733778);

  if ($node === NULL) {
    return 'Node 733778 not found, skipping deletion.';
  }

  $node->delete();

  return 'Deleted duplicate node 733778 (govcy-unified-design-system).';
}