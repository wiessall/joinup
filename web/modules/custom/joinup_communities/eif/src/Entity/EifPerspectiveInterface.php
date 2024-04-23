<?php

declare(strict_types=1);

namespace Drupal\eif\Entity;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Interface for EIF perspective meta-entities.
 */
interface EifPerspectiveInterface extends MetaEntityInterface, RdfSyncEntityInterface {}
