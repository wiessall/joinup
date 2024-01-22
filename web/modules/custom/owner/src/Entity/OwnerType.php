<?php

declare(strict_types = 1);

namespace Drupal\owner\Entity;

use Drupal\rdf_sync\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Owner type term class.
 */
class OwnerType extends Term implements OwnerTypeInterface {

  use RdfSyncEntityTrait;

}
