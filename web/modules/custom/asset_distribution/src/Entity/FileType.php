<?php

declare(strict_types=1);

namespace Drupal\asset_distribution\Entity;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

/**
 * Bundle class for file_type terms.
 */
class FileType extends Term implements TermInterface, RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
