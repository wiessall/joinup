<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model;

/**
 * Value object storing the API guesser result.
 */
class ApiGuess {

  public function __construct(
    public readonly string $pluginId,
    public readonly string $baseUrl,
  ) {}

}
