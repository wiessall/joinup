<?php

declare(strict_types=1);

namespace Drupal\joinup_bookmark\Plugin\ActionLink;

use Drupal\flag\Plugin\ActionLink\AJAXactionLink;

/**
 * Provides the AJAX link type for node entities.
 *
 * This class is an extension of the Reload link type, but modified to
 * provide AJAX links.
 *
 * @ActionLinkType(
 *   id = "node_bookmark_link",
 *   label = @Translation("Node bookmark link"),
 *   description = @Translation("An Node AJAX JavaScript request will be made without reloading the page.")
 * )
 */
class NodeBookmarkLink extends AJAXactionLink {

  use ActionLinkBookmarkTrait;

}
