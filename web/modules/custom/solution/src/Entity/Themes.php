<?php

declare(strict_types = 1);

namespace Drupal\solution\Entity;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

/**
 * Bundle class for themes terms.
 */
class Themes extends Term implements TermInterface, RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
