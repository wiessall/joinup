<?php

declare(strict_types=1);

namespace Drupal\topic\Entity;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Interface for topic entities in Portal.
 */
interface TopicInterface extends TermInterface, RdfSyncEntityInterface {}
