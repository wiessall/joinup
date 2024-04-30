<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a value resolver plugin.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class EuOssCatalogueResolver extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The label of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public Translation $label;

}
