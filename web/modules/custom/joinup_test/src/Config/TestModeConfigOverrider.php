<?php

declare(strict_types=1);

namespace Drupal\joinup_test\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\StorageInterface;
use Joinup\TestMode;
use Joinup\TestModeManager;

/**
 * Applies global config overrides during tests, depending on the test mode.
 */
class TestModeConfigOverrider implements ConfigFactoryOverrideInterface {

  /**
   * Config overrides to apply per test mode.
   */
  protected const array OVERRIDES = [
    TestMode::Playwright->value => [
      'oe_webtools_cookie_consent.settings' => [
        'banner_popup' => FALSE,
        'video_popup' => FALSE,
      ],
    ],
    TestMode::Behat->value => [
      'user.role.authenticated' => [
        'dependencies' => [
          'module' => ['content_translation' => 'content_translation'],
        ],
        'permissions' => ['translate editable entities' => 'translate editable entities'],
      ],
      'block.block.ventuno_language_picker' => [
        'visibility' => [
          'user_role' => [
            'roles' => [
              'authenticated' => 'authenticated',
              'anonymous' => 'anonymous',
            ],
          ],
        ],
      ],
      'block.block.iop_language_picker' => [
        'visibility' => [
          'user_role' => [
            'roles' => [
              'authenticated' => 'authenticated',
              'anonymous' => 'anonymous',
            ],
          ],
        ],
      ],
    ],
  ];

  /**
   * The current test_mode.
   */
  protected string|null $testMode;

  /**
   * Flattened array of overridden config names.
   */
  protected array $configNames = [];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {
    $this->testMode = TestModeManager::current()?->value;

    // Flatten all overridden config names across all test modes into one array.
    $config_names = [];
    foreach (self::OVERRIDES as $override) {
      $config_names = [...$config_names, ...array_keys($override)];
    }
    $this->configNames = array_unique($config_names);
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names): array {
    $overrides = [];

    if ($this->testMode && isset(self::OVERRIDES[$this->testMode])) {
      foreach (self::OVERRIDES[$this->testMode] as $config_name => $values) {
        if (\in_array($config_name, $names, TRUE)) {
          $overrides[$config_name] = $values;
        }
      }
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix(): string {
    return 'joinup_test';
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
    if (in_array($name, $this->configNames, TRUE)) {
      $metadata->addCacheableDependency($this->configFactory->get(TestModeManager::CONFIG_NAME));
    }
    return $metadata;
  }

}
