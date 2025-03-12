<?php

declare(strict_types=1);

namespace Drupal\search_api_arbitrary_facet\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ArbitraryFacet attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ArbitraryFacet extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
  ) {}

}
