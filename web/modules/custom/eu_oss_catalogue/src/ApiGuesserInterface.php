<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

/**
 * Interface of git API guesser service.
 */
interface ApiGuesserInterface {

  /**
   * Returns the ID of the hosting platform plugin which can handle the repo.
   *
   * @param string $repositoryUrl
   *   The repository URL.
   * @param bool $useCache
   *   Whether cache should be used or not. Defaults to TRUE.
   *
   * @return string|null
   *   The ID of the hosting platform plugin which is probably able to handle
   *   the given Git URL, NULL otherwise.
   *
   * @throws \InvalidArgumentException
   *   In case the provided URL string isn't a Git URL.
   */
  public function getHostingPlatformPluginId(string $repositoryUrl, bool $useCache = TRUE): ?string;

}
