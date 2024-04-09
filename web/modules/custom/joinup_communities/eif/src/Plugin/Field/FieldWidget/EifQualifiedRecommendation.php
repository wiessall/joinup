<?php

declare(strict_types=1);

namespace Drupal\eif\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eif\Entity\EifRecommendationInterface;
use Drupal\text\Plugin\Field\FieldWidget\TextareaWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for the 'eif_qualified_recommendation' field type.
 *
 * @FieldWidget(
 *   id = "eif_qualified_recommendation",
 *   label = @Translation("EIF Qualified Recommendation select"),
 *   field_types = {
 *     "eif_qualified_recommendation",
 *   },
 *   multiple_values = FALSE,
 * )
 */
class EifQualifiedRecommendation extends TextareaWidget {

  /**
   * Static cache for EIF recommendation terms.
   *
   * @var string[]
   */
  protected array $recommendationOptions;

  /**
   * Constructs a new widget plugin instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
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
    array $third_party_settings,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
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
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['target_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Recommendation'),
      '#title_display' => 'invisible',
      '#options' => $this->getRecommendationOptions(),
      '#default_value' => $items[$delta]->target_id,
      '#empty_option' => $this->t('- Select a Recommendation -'),
      '#empty_value' => '<none>',
      '#required' => $element['#required'],
    ];
    $element['#required'] = FALSE;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    return array_filter($values, fn(array $value): bool => $value['target_id'] !== '<none>');
  }

  /**
   * Builds a list of recommendation options.
   *
   * @return string[]
   *   A list suitable to be passed as options to a select form element.
   */
  protected function getRecommendationOptions(): array {
    if (!isset($this->recommendationOptions)) {
      $this->recommendationOptions = array_map(
        fn(EifRecommendationInterface $term): string => $term->getFullLabel(),
        $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
          'vid' => 'eif_recommendation',
        ]),
      );
    }
    return $this->recommendationOptions;
  }

}
