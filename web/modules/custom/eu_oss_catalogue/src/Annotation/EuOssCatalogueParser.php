<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Annotation;

use Attribute;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a parser plugin.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class EuOssCatalogueParser extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The label of the plugin.
   *
   * This property is translatable.
   */
  public Translation $label;

}
