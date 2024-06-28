<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Entity;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\node\Entity\Node;

/**
 * A bundle class for node entities.
 */
final class Solution extends Node implements SolutionInterface, RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
