<?php

declare(strict_types = 1);

namespace Drupal\joinup_taxonomy\Entity;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

/**
 * Bundle class for spatial terms.
 */
class Spatial extends Term implements TermInterface, RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
