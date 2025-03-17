<?php

declare(strict_types=1);

namespace Drupal\joinup_licence\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines an JoinupLicenceCompatibilityRule attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class JoinupLicenceCompatibilityRule extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly int $weight = 0,
  ) {}

}
