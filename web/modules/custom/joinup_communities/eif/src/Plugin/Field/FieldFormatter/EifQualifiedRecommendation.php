<?php

declare(strict_types=1);

namespace Drupal\eif\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\text\Plugin\Field\FieldFormatter\TextDefaultFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'text_default' formatter.
 *
 * @FieldFormatter(
 *   id = "eif_qualified_recommendation",
 *   label = @Translation("EIF Qualified Recommendation"),
 *   field_types = {
 *     "eif_qualified_recommendation",
 *   },
 * )
 */
class EifQualifiedRecommendation extends TextDefaultFormatter {

  /**
   * Constructs a FormatterBase object.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    foreach (parent::viewElements($items, $langcode) as $delta => $element) {
      /** @var \Drupal\eif\Entity\EifRecommendationInterface $term */
      if ($term = $storage->load($items[$delta]->getValue()['target_id'])) {
        $elements[] = [
          '#theme' => 'eif_qualified_recommendation',
          '#label' => $term->getFullLabel(),
          '#description' => $element,
        ];
      }
    }
    return $elements;
  }

}
