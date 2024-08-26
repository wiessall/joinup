<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\eu_oss_catalogue\Model\ApiGuess;

/**
 * Interface of git API guesser service.
 */
interface ApiGuesserInterface {

  /**
   * Returns the ID of the hosting platform plugin which can handle the repo.
   *
   * @param string $repositoryUrl
   *   The repository URL.
   *
   * @return \Drupal\eu_oss_catalogue\Model\ApiGuess|null
   *   A value object, storing the ID and the base URL of the code hosting
   *   platform plugin and the which is probably able to handle the given URL,
   *   NULL otherwise.
   *
   * @throws \InvalidArgumentException
   *   In case the provided URL string isn't a Git URL.
   */
  public function getHostingPlatformApi(string $repositoryUrl): ?ApiGuess;

}
