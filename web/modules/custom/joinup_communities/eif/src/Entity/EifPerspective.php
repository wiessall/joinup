<?php

declare(strict_types=1);

namespace Drupal\eif\Entity;

use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Bundle class for EIF perspective meta-entities.
 */
class EifPerspective extends MetaEntity implements EifPerspectiveInterface {

  use RdfSyncEntityTrait;

}
