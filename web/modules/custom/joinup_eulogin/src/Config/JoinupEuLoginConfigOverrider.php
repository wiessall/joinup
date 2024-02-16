<?php

declare(strict_types=1);

namespace Drupal\joinup_eulogin\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\State\StateInterface;

/**
 * Overrides some CAS settings.
 */
class JoinupEuLoginConfigOverrider implements ConfigFactoryOverrideInterface {

  /**
   * Constructs a new config overrider service instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(protected StateInterface $state) {
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names): array {
    return [
      'cas.settings' => [
        'advanced' => [
          'debug_log' => $this->state->get('joinup_eulogin.cas_debug', FALSE),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix(): string {
    return 'joinup_eulogin';
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION): ?StorableConfigBase {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name): CacheableMetadata {
    return new CacheableMetadata();
  }

}
