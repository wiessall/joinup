<?php

declare(strict_types=1);

namespace Drupal\joinup_bookmark\Plugin\ActionLink;

use Drupal\flag\Plugin\ActionLink\AJAXactionLink;
use Drupal\joinup_flag\Plugin\ActionLink\RdfActionLinkTrait;

/**
 * Provides the AJAX link type for RDF entities.
 *
 * This class is an extension of the Reload link type, but modified to
 * provide AJAX links.
 *
 * @ActionLinkType(
 *   id = "rdf_bookmark_link",
 *   label = @Translation("RDF bookmark link"),
 *   description = @Translation("An RDF AJAX JavaScript request will be made without reloading the page.")
 * )
 */
class RdfBookmarkLink extends AJAXactionLink {

  use RdfActionLinkTrait;
  use ActionLinkBookmarkTrait;

}
