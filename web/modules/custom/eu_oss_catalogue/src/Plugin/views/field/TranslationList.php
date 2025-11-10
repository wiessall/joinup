<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\views\field;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders all translations for an entity.
 */
#[ViewsField("translation_list")]
class TranslationList extends FieldPluginBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
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
