<?php

declare(strict_types=1);

namespace Drupal\joinup_test\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\State\StateInterface;

/**
 * Config overrider use to disable anti-spam guard.
 */
class JoinupTestConfigOverrider implements ConfigFactoryOverrideInterface {

  /**
   * Constructs a new config overrider service instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   */
  public function __construct(
    protected StateInterface $state,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names): array {
    if (in_array('antibot.settings', $names, TRUE) && !$this->state->get('joinup_test.antispam.enabled', FALSE)) {
      return [
        'antibot.settings' => [
          // We cannot set an empty array as the indexed arrays are merged, and
          // we'll get again the list from storage.
          'form_ids' => NULL,
        ],
      ];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix(): string {
    return 'joinup_core';
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
    $metadata = new CacheableMetadata();
    if ($name === 'antibot.settings') {
      $metadata->addCacheableDependency($this->configFactory->get($name));
    }
    return $metadata;
  }

}
