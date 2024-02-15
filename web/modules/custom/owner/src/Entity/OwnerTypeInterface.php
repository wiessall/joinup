<?php

declare(strict_types=1);

namespace Drupal\owner\Entity;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Owner type term interface.
 */
interface OwnerTypeInterface extends TermInterface, RdfSyncEntityInterface {
}
