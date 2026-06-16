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
   * Value for language_alterable attribute.
   */
  protected const array LANGUAGE_ALTERABLE = [
    'language_alterable' => TRUE,
  ];

  protected const array LANGUAGE_PICKER_CONFIG = [
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
    'language.content_settings.assessment_statement.legal' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.assessment_statement.organisational' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.assessment_statement.semantic' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.assessment_statement.technical' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.menu_link_content.menu_link_content' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.meta_entity.collection_settings' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.meta_entity.eif_perspective' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.meta_entity.solution_settings' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.meta_entity.topic' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.collection' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.contact_information' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.custom_page' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.distribution' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.document' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.event' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.glossary' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.news' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.owner' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.release' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.node.solution' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.poll.poll' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.poll_choice.poll_choice' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.redirect.redirect' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.taxonomy_term.domain' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.taxonomy_term.language' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.taxonomy_term.owner_type' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.taxonomy_term.solution_classification' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.taxonomy_term.spatial' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.taxonomy_term.status' => self::LANGUAGE_ALTERABLE,
    'language.content_settings.taxonomy_term.topic' => self::LANGUAGE_ALTERABLE,
  ];

  /**
   * Config overrides to apply per test mode.
   */
  protected const array OVERRIDES = [
    TestMode::Playwright->value => [
      'oe_webtools_cookie_consent.settings' => [
        'banner_popup' => FALSE,
        'video_popup' => FALSE,
      ],
    ] + self::LANGUAGE_PICKER_CONFIG,
    TestMode::Behat->value => self::LANGUAGE_PICKER_CONFIG,
    TestMode::PhpUnit->value => self::LANGUAGE_PICKER_CONFIG,
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
