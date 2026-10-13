<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Exception;

/**
 * Thrown when a mutation reported success but its own check failed.
 *
 * Callers run inside a transaction that this rolls back. It is logged at
 * error level before it is thrown, because it means a bug, not bad input.
 */
final class InvariantViolationException extends \RuntimeException {}
