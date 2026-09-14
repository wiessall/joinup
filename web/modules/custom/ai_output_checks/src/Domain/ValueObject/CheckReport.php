<?php

namespace Drupal\ai_output_checks\Domain\ValueObject;

final class CheckReport {

  /** @var Finding[] */
  private array $findings = [];

  public function __construct(
    public readonly string $fieldName,
  ) {}

  public function add(Finding $finding): void {
    $this->findings[] = $finding;
  }

  /** @return Finding[] */
  public function findings(): array {
    return $this->findings;
  }

  public function isOk(): bool {
    return count($this->findings) === 0;
  }

}
