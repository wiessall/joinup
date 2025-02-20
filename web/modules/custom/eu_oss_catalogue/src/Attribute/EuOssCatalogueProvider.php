<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a EU OSS Catalogue provider.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EuOssCatalogueProvider extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly string $parser,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
    public readonly ?string $configuredBy = NULL,
  ) {}

}
