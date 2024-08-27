<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\joinup_oss_catalogue\Entity\JoinupOssSolution;
use Drupal\pluginreference\Plugin\Field\FieldType\PluginReferenceFieldItemListInterface;

/**
 * Plugin implementation 'joinup_oss_catalogue_link_to_solution_search'.
 *
 * @FieldFormatter(
 *   id = "joinup_oss_catalogue_link_to_solution_search",
 *   label = @Translation("Link to solution search "),
 *   field_types = {
 *     "plugin_reference",
 *     "entity_reference",
 *   }
 * )
 */
class LinkToSolutionSearch extends FormatterBase {

  const SEARCH_PAGE_ROUTE = 'view.search_oss_catalogue.search';

  const DEFAULT_LINK = [
    '#type' => 'link',
    '#attributes' => ['referer' => 'nofollow'],
  ];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'facet_id' => '',
      'use_label' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * Returns a form to configure settings for the formatter.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $formDtate
   *   The current state of the form.
   *
   * @return array
   *   The form elements for the formatter settings.
   */
  public function settingsForm(array $form, FormStateInterface $formDtate): array {
    $elements = parent::settingsForm($form, $formDtate);

    $elements['facet_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facet ID'),
      '#default_value' => $this->getSetting('facet_id'),
      '#description' => $this->t("Leave blank to use the field's machine name."),
    ];
    $elements['use_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use label instead of ID in search link'),
      '#default_value' => $this->getSetting('use_label'),
    ];

    return $elements;
  }

  /**
   * Returns the actual facet ID configuration.
   *
   * @return string
   *   The actual facet ID to use in the link query.
   */
  protected function getFacetId(): string {
    return $this->getSetting('facet_id') ?: $this->fieldDefinition->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary[] = $this->t('Facet ID is: %facet_id', [
      '%facet_id' => $this->getFacetId(),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    return match($this->fieldDefinition->getType()) {
      'entity_reference' => $this->viewEntityReferenceElements($items, $langcode),
      'plugin_reference' => $this->viewPluginReferenceElements($items, $langcode),
      default => [],
    };
  }

  /**
   * Builds a renderable array for an entity reference field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array for $items, as an array of child elements keyed by
   *   consecutive numeric indexes starting from 0.
   */
  protected function viewEntityReferenceElements(FieldItemListInterface $items, string $langcode): array {
    assert($items instanceof EntityReferenceFieldItemListInterface);
    $renderable = [];
    $facetId = $this->getFacetId();
    foreach ($items as $delta => $item) {
      $linkTitle = $this->getSetting('use_label')
        ? $item->entity->label()
        : $item->entity->id();
      $renderable[$delta] = [
        '#title' => $item->entity->label(),
        '#url' => Url::fromRoute(
          route_name: static::SEARCH_PAGE_ROUTE,
          options: [
            'query' => [
              'f' => ["$facetId:" . $linkTitle],
            ],
          ],
        ),
      ] + static::DEFAULT_LINK;
    }

    return $renderable;
  }

  /**
   * Builds a renderable array for a plugin reference field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array for $items, as an array of child elements keyed by
   *   consecutive numeric indexes starting from 0.
   */
  protected function viewPluginReferenceElements(FieldItemListInterface $items, string $langcode): array {
    assert($items instanceof PluginReferenceFieldItemListInterface);
    $renderable = [];
    $facetId = $this->getFacetId();
    foreach ($items->referencedPlugins() as $delta => $plugin) {
      $labelFromDefinition = $plugin->getPluginDefinition()['label'] ?? '';
      $linkTitle = $this->getSetting('use_label') && $labelFromDefinition
        ? $labelFromDefinition
        : $plugin->getPluginId();
      $renderable[$delta] = [
        '#title' => $labelFromDefinition ?: $plugin->getPluginId(),
        '#url' => Url::fromRoute(
          route_name: static::SEARCH_PAGE_ROUTE,
          options: [
            'query' => [
              'f' => ["$facetId:" . $linkTitle],
            ],
          ],
        ),
      ] + static::DEFAULT_LINK;
    }

    return $renderable;
  }

  /**
   * Returns if the formatter can be used for the provided field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition that should be checked.
   *
   * @return bool
   *   TRUE if the formatter can be used, FALSE otherwise.
   */
  public static function isApplicable(FieldDefinitionInterface $fieldDefinition): bool {
    $entityBundleInfo = \Drupal::service('entity_type.bundle.info');
    $currentBundleInfo = $entityBundleInfo->getBundleInfo($fieldDefinition->getTargetEntityTypeId());
    $targetBundle = $fieldDefinition->getTargetBundle();
    return array_key_exists($targetBundle, $currentBundleInfo)
      && isset($currentBundleInfo[$targetBundle]['class'])
      && $currentBundleInfo[$targetBundle]['class'] === JoinupOssSolution::class;
  }

}
