<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\LanguageFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Translation filter for OSS admin view.
 *
 * This filter falls back to filtering for default language version when there
 * was no limitation set.
 */
#[ViewsFilter("eu_oss_catalogue_translation_filter")]
class TranslationFilter extends LanguageFilter {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    LanguageManagerInterface $language_manager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $language_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input): bool {
    $identifier = $this->options['expose']['identifier'];
    // We'll ask for default translations if there is no limitation on langcode.
    if ($input[$identifier] == 'All') {
      return TRUE;
    }
    return parent::acceptExposedInput($input);
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $currentValue = $this->value ?? NULL;
    if ($currentValue) {
      parent::query();
      return;
    }

    $this->ensureMyTable();
    $defaultLanguageKey = $this->entityTypeManager->getDefinition($this->getEntityType())->getKey('default_langcode');
    $this->query->addWhere($this->options['group'], "$this->tableAlias.$defaultLanguageKey", 1);
  }

}
