<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a parser plugin.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EuOssCatalogueParser extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $deriver = NULL,
  ) {}

}
