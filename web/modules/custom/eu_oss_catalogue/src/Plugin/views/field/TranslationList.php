<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\views\field;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders all translations for an entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("translation_list")
 */
class TranslationList extends FieldPluginBase {

  /**
   * Constructs TranslationList.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param array $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    array $pluginDefinition,
    protected LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array {
    $entity = $values->_entity;

    $list = [];
    foreach ($this->languageManager->getLanguages() as $langcode) {
      if ($entity->hasTranslation($langcode->getId())) {
        $list[] = strtoupper($langcode->getId());
      }
    }

    return ['#markup' => implode(', ', $list)];
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // Don't call parent method.
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable(): bool {
    return FALSE;
  }

}
