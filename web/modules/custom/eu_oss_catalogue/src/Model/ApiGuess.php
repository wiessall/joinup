<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model;

/**
 * Value object storing the API guesser result.
 */
class ApiGuess {

  /**
   * Constructs a new value object.
   *
   * @param string $pluginId
   *   The guessed code hosting platform plugin ID.
   * @param string $baseUrl
   *   The code hosting platform base URL.
   */
  public function __construct(
    public readonly string $pluginId,
    public readonly string $baseUrl,
  ) {}

}
