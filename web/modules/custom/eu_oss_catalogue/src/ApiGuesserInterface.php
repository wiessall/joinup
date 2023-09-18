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
   *   The ID of the hostign platform plugin which is probably able to handle
   *   the given git URL, NULL otherwise.
   */
  public function getHostingPlatformPluginId(string $repositoryUrl, bool $useCache = TRUE): ?string;

}
