<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy\Entity;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Bundle class for terms mapped to RDF.
 */
class MappedTerm extends Term implements RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
