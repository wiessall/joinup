<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The SparqlFormat attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SparqlFormat extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?int $weight = NULL,
    public readonly ?bool $inline = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
