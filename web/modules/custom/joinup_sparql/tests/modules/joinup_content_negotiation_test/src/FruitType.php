<?php

declare(strict_types=1);

namespace Drupal\joinup_content_negotiation_test;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Bundle class.
 */
class FruitType extends Term implements RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
