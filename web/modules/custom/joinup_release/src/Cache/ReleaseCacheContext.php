<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the joinup_release cache context service.
 *
 * Cache context ID: 'joinup_release'.
 */
class ReleaseCacheContext implements CacheContextInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a new ReleaseCacheContext service.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): string|TranslatableMarkup {
    return t('Release');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    /** @var \Drupal\joinup_release\Entity\ReleaseInterface $release */
    if (($release = $this->requestStack->getCurrentRequest()->get('node')) && $release->bundle() === 'release') {
      return 'joinup_release.' . $release->id();
    }
    return 'joinup_release.none';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return new CacheableMetadata();
  }

}
