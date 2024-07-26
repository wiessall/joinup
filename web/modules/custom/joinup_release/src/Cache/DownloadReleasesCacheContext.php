<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Cache;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\Context\RouteNameCacheContext;

/**
 * Checks if the current route is joinup_release.solution_release.overview.
 *
 * Cache context ID: 'route.name.is_download_releases_route'.
 */
class DownloadReleasesCacheContext extends RouteNameCacheContext {

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): MarkupInterface {
    return t("Is 'Download releases' route");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    return 'is_download_releases_route.' . (int) ($this->routeMatch->getRouteName() === 'joinup_release.solution_release.overview');
  }

}
