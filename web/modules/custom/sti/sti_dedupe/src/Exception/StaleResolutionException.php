<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Exception;

/**
 * Thrown when a curator rules on claims that changed since the form loaded.
 *
 * A re-import between loading and submitting the resolution form can add or
 * remove claimed values. Saving the ruling anyway would attach verdicts to a
 * set of values the curator never saw.
 */
final class StaleResolutionException extends \RuntimeException {}
