<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolve the lang codes known by Drupal to full name.
 *
 * @FieldFormatter(
 *   id = "joinup_language_code",
 *   label = @Translation("Language code resolver"),
 *   field_types = {
 *     "string",
 *     "text",
 *   },
 *   settings = {}
 * )
 */
class LanguageCodeFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs an LanguageCodeFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, protected LanguageManagerInterface $languageManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    $languages = $this->languageManager->getStandardLanguageList();
    foreach ($items as $delta => $item) {
      $codes = explode(",", $item->value);
      foreach ($codes as $id => $code) {
        $codes[$id] = isset($languages[$code]) ? $languages[$code][0] : $code;
      }

      $element[$delta] = [
        '#markup' => implode(",", $codes),
      ];
    }

    return $element;
  }

}
