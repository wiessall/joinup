<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a code hosting platform plugin.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class EuOssCatalogueHostingPlatform extends Plugin {

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
