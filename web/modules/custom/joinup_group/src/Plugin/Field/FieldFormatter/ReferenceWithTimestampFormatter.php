<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity reference label' formatter.
 */
#[FieldFormatter(
  id: 'joinup_group_reference_with_timestamp',
  label: new TranslatableMarkup('Label with timestamp'),
  description: new TranslatableMarkup('Display the label of the referenced entities with the raw timestamp.'),
  field_types: [
    'joinup_group_reference_with_timestamp',
  ],
)]
class ReferenceWithTimestampFormatter extends EntityReferenceLabelFormatter {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    foreach ($items as $delta => $item) {
      $timestamp = $items->get($delta)->timestamp ?: NULL;
      if (empty($timestamp)) {
        continue;
      }

      $elements[$delta] = [
        'label' => $elements[$delta] + ['#suffix' => ' '],
      ];
      $elements[$delta]['timestamp'] = [
        '#prefix' => ' ',
        '#markup' => $this->t(
          '@timestamp (@rfc-3339)',
          [
            '@timestamp' => $timestamp,
            '@rfc-3339' => $this->dateFormatter->format((int) $timestamp, 'custom', \DateTime::RFC3339),
          ],
        ),
      ];
    }
    return $elements;
  }

}
