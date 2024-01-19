<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines an RDF URI generator plugin annotation.
 *
 * @see plugin_api
 *
 * @ingroup plugin_api
 *
 * @Annotation
 */
class RdfUriGenerator extends Plugin {

  /**
   * The RDF URI generator plugin ID.
   */
  public string $id;

  /**
   * The human-readable name of the RDF URI generator plugin.
   *
   * @ingroup plugin_translatable
   */
  public Translation $label;

}
