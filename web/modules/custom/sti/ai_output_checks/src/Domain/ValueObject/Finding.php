<?php

declare(strict_types=1);

namespace Drupal\ai_output_checks\Domain\ValueObject;

/**
 *
 */
final class Finding {

  public function __construct(
    public readonly string $checkId,
    // info|warning|error|blocker.
    public readonly string $severity,
    public readonly string $message,
    public readonly string $evidence = '',
    public readonly array $data = [],
  ) {}

}
