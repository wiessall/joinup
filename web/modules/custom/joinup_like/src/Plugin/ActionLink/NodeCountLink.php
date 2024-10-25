<?php

declare(strict_types=1);

namespace Drupal\joinup_like\Plugin\ActionLink;

use Drupal\flag_count\Plugin\ActionLink\CountLink;

/**
 * Provides the Count link type for node entities.
 *
 * This class is an extension of the Ajax link type, but modified to
 * provide flag count.
 *
 * @ActionLinkType(
 *   id = "count_link_node",
 *   label = @Translation("Portal Count link node entity"),
 *   description = "An AJAX action link which displays the count with the flag."
 * )
 */
class NodeCountLink extends CountLink {

  use ActionLinkArchivedTrait;

}
