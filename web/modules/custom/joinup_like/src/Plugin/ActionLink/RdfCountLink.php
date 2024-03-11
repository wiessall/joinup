<?php

declare(strict_types=1);

namespace Drupal\joinup_like\Plugin\ActionLink;

use Drupal\flag_count\Plugin\ActionLink\CountLink;
use Drupal\joinup_flag\Plugin\ActionLink\RdfActionLinkTrait;

/**
 * Provides the Count link type for rdf entities.
 *
 * This class is an extension of the Ajax link type, but modified to
 * provide flag count.
 *
 * @ActionLinkType(
 *   id = "count_link_rdf",
 *   label = @Translation("Joinup Count link Rdf entity"),
 *   description = "An AJAX action link which displays the count with the flag."
 * )
 */
class RdfCountLink extends CountLink {

  use ActionLinkArchivedTrait;
  use RdfActionLinkTrait;
}
