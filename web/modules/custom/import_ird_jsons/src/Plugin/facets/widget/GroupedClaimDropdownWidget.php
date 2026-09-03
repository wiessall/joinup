<?php

namespace Drupal\import_ird_jsons\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * Renders claim_facet triples as grouped per-property dropdowns.
 *
 * The claim_facet Solr field contains concatenated values like
 * "stf::awards::Yes", "stf::country::Kenya". This widget groups them by
 * property and renders one <select> per property, matching the visual
 * appearance of joinup's existing facet dropdowns.
 *
 * Because AdjustGroupContentSubscriber scopes the Solr query to the
 * current collection's solutions, only that collection's triples appear
 * in the facet results. No client-side collection filtering is needed.
 *
 * URL format: ?f[0]=claim_facet:stf::awards::Yes
 * This is standard Facets URL processor behavior — the full triple is
 * the facet value, and the Facets module handles encoding/decoding.
 *
 * @FacetsWidget(
 *   id = "grouped_claim_dropdown",
 *   label = @Translation("Grouped claim dropdowns"),
 *   description = @Translation("Splits collection::property::value triples into per-property dropdowns."),
 * )
 */
class GroupedClaimDropdownWidget extends WidgetPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'show_numbers' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet): array {
    $form['show_numbers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show counts'),
      '#default_value' => $this->getConfiguration()['show_numbers'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet): array {
    $results = $facet->getResults();
    $show_numbers = $this->getConfiguration()['show_numbers'];

    // Group results by property.
    $grouped = [];
    $active_values = [];

    foreach ($results as $result) {
      $raw = $result->getRawValue();
      $parts = explode('::', $raw, 3);
      if (count($parts) !== 3) {
        continue;
      }

      [, $property, $value] = $parts;

      // Build a human-readable property label.
      $property_label = ucwords(str_replace('_', ' ', $property));

      $grouped[$property]['label'] = $property_label;
      $grouped[$property]['items'][] = [
        'raw'    => $raw,
        'value'  => $value,
        'count'  => $result->getCount(),
        'active' => $result->isActive(),
        'url'    => $result->getUrl(),
      ];

      if ($result->isActive()) {
        $active_values[$property] = $raw;
      }
    }

    // Sort properties alphabetically by label.
    uasort($grouped, fn($a, $b) => strcmp($a['label'], $b['label']));

    // Build render array with one dropdown per property.
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['claim-facets-grouped']],
    ];

    foreach ($grouped as $property_slug => $group) {
      $options = ['' => $this->t('- Any @label -', ['@label' => $group['label']])];
      $urls = [];
      $default = '';

      foreach ($group['items'] as $item) {
        $label = $item['value'];
        if ($show_numbers) {
          $label .= ' (' . $item['count'] . ')';
        }
        $options[$item['raw']] = $label;
        $urls[$item['raw']] = $item['url'] instanceof Url
          ? $item['url']->toString()
          : (string) $item['url'];

        if ($item['active']) {
          $default = $item['raw'];
        }
      }

      // If this property has an active filter, we need a reset URL.
      if ($default !== '' && isset($urls[$default])) {
        // The active item's URL toggles it off (standard Facets behavior).
        $options[''] = $this->t('- Any @label -', ['@label' => $group['label']]);
        $urls[''] = $urls[$default];
      }

      $dropdown_id = 'claim-facet-' . $property_slug;

      $build[$property_slug] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['claim-facet-dropdown-wrapper']],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'label',
          '#value' => $group['label'],
          '#attributes' => [
            'for' => $dropdown_id,
            'class' => ['claim-facet-label'],
          ],
        ],
        'select' => [
          '#type' => 'select',
          '#options' => $options,
          '#default_value' => $default,
          '#value' => $default,        // ← add this
          '#attributes' => [
            'id' => $dropdown_id,
            'class' => ['claim-facet-select'],
            // URLs are passed as data attribute for JS-driven navigation.
            'data-urls' => json_encode($urls),
          ],
        ],
      ];
    }

    // Attach JS for dropdown navigation.
    $build['#attached']['library'][] = 'import_ird_jsons/claim_facet_dropdown';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyRequired($name, $type): bool {
    if ($name === 'url_processor_handler' && $type === 'processors') {
      return TRUE;
    }
    return FALSE;
  }

}
