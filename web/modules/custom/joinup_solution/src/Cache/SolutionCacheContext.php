<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the solution cache context service.
 *
 * Cache context ID: 'solution'.
 */
class SolutionCacheContext implements CacheContextInterface {

  /**
   * Constructs a new SolutionCacheContext service.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(protected RequestStack $request_stack) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): string {
    return 'Solution';
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    if (($solution = $this->requestStack->getCurrentRequest()->get('node')) && $solution->bundle() == 'solution') {
      return 'solution.' . $solution->id();
    }
    return 'solution.none';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return new CacheableMetadata();
  }

}
