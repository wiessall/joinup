<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\FilterPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Glossary plain' formatter.
 */
#[FieldFormatter(
  id: 'collection_plain_glossary',
  label: new TranslatableMarkup('Plain text with glossary proccessing'),
  field_types: [
    'string',
    'text_long',
  ],
)]
final class PlainGlossaryFormatter extends FormatterBase {

  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    protected readonly FilterPluginManager $filterPluginManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.filter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    foreach ($items as $delta => $item) {
      $filter = $this->filterPluginManager->createInstance('collection_glossary');
      $value = $filter->process($item->value, $langcode);
      $filter = $this->filterPluginManager->createInstance('linkit', [
        'settings' => ['title' => FALSE],
      ]);
      $value = $filter->process($value, $langcode);
      $element[$delta] = [
        '#markup' => $value,
      ];
    }
    return $element;
  }

}
