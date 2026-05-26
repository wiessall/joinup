<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement;

/**
 * Result of running the enhancement pipeline on an entity.
 *
 * Status values:
 *   - 'approved'        : judge signed off
 *   - 'max_iterations'  : loop exhausted without approval
 *   - 'error'           : pipeline failed mid-run
 */
final class EnhancementResult {

  public function __construct(
    public readonly string $status,
    public readonly string $content,
    public readonly int $iterations,
    public readonly array $history = [],
    public readonly ?string $errorMessage = NULL,
  ) {}

  public function isApproved(): bool {
    return $this->status === 'approved';
  }

  public function toArray(): array {
    return [
      'status' => $this->status,
      'content' => $this->content,
      'iterations' => $this->iterations,
      'history' => $this->history,
      'error' => $this->errorMessage,
    ];
  }

}
