<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a SPARQL format item annotation object.
 *
 * @see \Drupal\sparql_endpoint\SparqlFormatManager
 * @see plugin_api
 *
 * @Annotation
 */
class SparqlFormat extends Plugin {

  /**
   * The plugin ID/format.
   *
   * @var string
   */
  public string $id;

  /**
   * The weight of the plugin in its group.
   *
   * @var int
   */
  public int $weight;

  /**
   * The mimeType.
   *
   * @var string
   */
  public string $mimeType;

  /**
   * The human-readable name of the plugin type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public Translation $label;

}
