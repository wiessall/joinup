<?php

declare(strict_types=1);

namespace Drupal\search_api_field\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a SearchApiFieldFilter attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SearchApiFieldFilter extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly array $fields = [],
    public readonly array $data_types = [],
    public readonly ?string $deriver = NULL,
  ) {}

}
