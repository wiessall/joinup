<?php

declare(strict_types=1);

namespace Drupal\rdf_sync_test;

use Drupal\node\Entity\Node;
use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityTrait;

/**
 * Bundle class for 'page' nodes.
 */
class Page extends Node implements RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
