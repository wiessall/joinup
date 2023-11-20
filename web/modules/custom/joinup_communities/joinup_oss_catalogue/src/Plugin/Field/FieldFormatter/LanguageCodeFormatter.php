<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter which strips all HTML.
 *
 * This is tailored to the use case of Joinup. It relies on a custom text filter
 * format `stripped_html` which includes all of the filters that are commonly
 * used in Joinup, such as Joinup Video, Glossary items, etc.
 *
 * The goal of this is to be able to not only strip vanilla HTML elements that
 * are present in the content but also placeholder tokens which are included by
 * CKEditor to mark the location of embedded media and other content.
 *
 * This prevents for example JSON metadata to be shown to the end user in places
 * where only plain text is desired (for example in tiles or teasers).
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
   * @param \Drupal\Core\Locale\CountryManagerInterface $countryManager
   *   The country manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, protected CountryManagerInterface $countryManager) {
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
      $container->get('country_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    $countries = $this->countryManager->getList();
    foreach ($items as $delta => $item) {
      $codes = explode(",", $item->value);

      foreach ($codes as $id => $code) {
        $codes[$id] = $countries[strtoupper($code)] ?? $code;
      }

      $element[$delta] = [
        '#markup' => implode(",", $codes),
      ];
    }

    return $element;
  }

}
