<?php

declare(strict_types=1);

namespace Drupal\joinup_licence\Entity;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

/**
 * Bundle class for licence_type terms.
 */
class LicenceType extends Term implements TermInterface, RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
