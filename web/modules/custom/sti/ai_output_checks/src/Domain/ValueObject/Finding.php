<?php

namespace Drupal\ai_output_checks\Domain\ValueObject;

final class Finding {

  public function __construct(
    public readonly string $checkId,
    public readonly string $severity, // info|warning|error|blocker
    public readonly string $message,
    public readonly string $evidence = '',
    public readonly array $data = [],
  ) {}

}
