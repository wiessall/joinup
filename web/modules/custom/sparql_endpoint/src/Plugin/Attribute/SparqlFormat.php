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

  /**
   * Constructs a SparqlFormat attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param class-string|null $deriver
   *   The deriver class.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The administrative label of the plugin.
   * @param int|null $weight
   *   The weight.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?string $deriver = NULL,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?int $weight = NULL,
  ) {}

}
