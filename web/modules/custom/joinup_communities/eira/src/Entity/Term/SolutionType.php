<?php

declare(strict_types=1);

namespace Drupal\eira\Entity\Term;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * A bundle class for taxonomy_term entities.
 */
final class SolutionType extends Term implements RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
