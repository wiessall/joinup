<?php

declare(strict_types=1);

namespace Drupal\eif\Entity;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

/**
 * Bundle class for eif_recommendation terms.
 */
class EifRecommendation extends Term implements TermInterface, RdfSyncEntityInterface {

  use RdfSyncEntityTrait;

}
