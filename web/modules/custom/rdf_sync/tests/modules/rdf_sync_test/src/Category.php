<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync_test;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Bundle class for 'category' terms.
 */
class Category extends Term implements RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
