<?php

namespace Drupal\ai_output_checks\Service;

final class UrlCheckResult {
  public function __construct(
    public readonly bool $ok,
    public readonly ?int $httpStatus = NULL,
    public readonly string $reason = '',
  ) {}
}
