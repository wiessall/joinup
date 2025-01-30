<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Attribute;

use Attribute;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a EU OSS Catalogue provider.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class EuOssCatalogueProvider extends Plugin {

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

  /**
   * The parser used to convert data.
   */
  public string $parser;

  /**
   * A short description of the provider plugin.
   *
   * This property is translatable.
   */
  public ?Translation $description = NULL;

  /**
   * The config entity type ID that configures this plugin, if any.
   */
  public ?string $configuredBy = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $id, Translation $label, string $parser, ?Translation $description, ?string $configuredBy) {
    $this->id = $id;
    $this->label = $label;
    $this->parser = $parser;
    $this->description = $description;
    $this->configuredBy = $configuredBy;
  }

}
