<?php

declare(strict_types=1);

namespace Drupal\assessments\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Enable page cache for some GET public REST resources.
 */
class AllowPublicResources implements RequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request): ?string {
    // Check the end of the path to allow language prefix.
    $pattern = '#/api/assessments/v1/(assessment/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|organisation|asset)$#';
    if (preg_match($pattern, $request->getPathInfo())) {
      return static::ALLOW;
    }
    return NULL;
  }

}
