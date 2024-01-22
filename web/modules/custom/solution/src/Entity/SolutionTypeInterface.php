<?php

declare(strict_types = 1);

namespace Drupal\solution\Entity;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Interface for eira terms.
 */
interface SolutionTypeInterface extends TermInterface, RdfSyncEntityInterface {
}
