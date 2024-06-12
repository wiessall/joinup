<?php

declare(strict_types=1);

namespace Drupal\dashboard\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\State\StateInterface;
use Drupal\user\UserInterface;

/**
 * Overrides site config based on moderators input.
 */
class DashboardConfigOverrider implements ConfigFactoryOverrideInterface {

  /**
   * Constructs a new config overrider service instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
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
    return [
      'user.settings' => [
        'register' => $this->state->get('joinup_eulogin.accounts_approve', FALSE) ? UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL : UserInterface::REGISTER_VISITORS,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix(): string {
    return 'joinup_dashboard';
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
    if ($name === 'user.settings') {
      $metadata->addCacheableDependency($this->configFactory->get($name));
    }
    return $metadata;
  }

}
