<?php

declare(strict_types = 1);

namespace Drupal\solution\Entity;

use Drupal\rdf_sync\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Bundle class for eira terms.
 */
class SolutionType extends Term implements SolutionTypeInterface {

  use RdfSyncEntityTrait;

}
