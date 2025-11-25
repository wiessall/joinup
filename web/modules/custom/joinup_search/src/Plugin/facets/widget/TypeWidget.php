<?php

declare(strict_types=1);

namespace Drupal\joinup_search\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * The type widget.
 *
 * @FacetsWidget(
 *   id = "type",
 *   label = @Translation("Render the type facet"),
 *   description = @Translation("A widget that shows some of the results as tabs"),
 * )
 */
class TypeWidget extends WidgetPluginBase {

  /**
   * List of result which are displayed as tab.
   *
   * @var \Drupal\facets\Result\ResultInterface[]
   */
  protected array $resultTabs = [];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['tabs' => 0] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet): array {
    $form = parent::buildConfigurationForm($form, $form_state, $facet);

    $config = $this->getConfiguration();
    // @todo replace with a textfield with validation.
    $values = range(1, 5);
    $form['tabs'] = [
      '#type' => 'select',
      '#title' => $this->t('Tabs'),
      '#description' => $this->t('The number of items to show separately as tabs with icon.'),
      '#options' => [0 => $this->t('- All -')] + array_combine($values, $values),
      '#default_value' => $config['tabs'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet): array {
    $this->facet = $facet;
    $config = $this->getConfiguration();

    $results = array_values($facet->getResults());
    $big_icons = [];
    foreach (array_splice($results, 0, $config['tabs'] ?: NULL) as $result) {
      $this->resultTabs[] = $result;
      $big_icons[] = $this->buildSingleResult($result);
    }

    $items = [];
    foreach ($results as $result) {
      $items[] = $this->buildSingleResult($result);
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'data-drupal-facet-id' => $facet->id(),
      ],
      '#cache' => [
        'contexts' => [
          'url.path',
          'url.query_args',
        ],
      ],
      'big_icons' => [
        '#theme' => !empty($items) ? 'facets_type_wrapper__more' : 'facets_type_wrapper',
        '#items' => $big_icons,
      ],
      'others' => [
        '#theme' => 'item_list__facets',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Builds a single result item to a renderable array.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   The result item.
   *
   * @return array
   *   The facet result item as a render array.
   */
  protected function buildSingleResult(ResultInterface $result): array {
    if (empty($result->getUrl())) {
      return $this->buildResultItem($result);
    }
    else {
      return $this->buildListItems($this->facet, $result);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildResultItem(ResultInterface $result): array {
    $count = $result->getCount();
    $theme = in_array($result, $this->resultTabs) ? 'facets_type_result_item' : 'facets_result_item';
    if ($result->isActive()) {
      $theme .= '__active';
    }

    return [
      '#theme' => $theme,
      '#value' => $result->getDisplayValue(),
      '#show_count' => $this->getConfiguration()['show_numbers'] && ($count !== NULL),
      '#count' => $count,
      '#type' => array_reverse(explode(':', $result->getRawValue()))[0],
      '#facet' => $result->getFacet(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildListItems(FacetInterface $facet, ResultInterface $result): array {
    $classes = ['facet-item'];
    if ($children = $result->getChildren()) {
      $items = $this->prepareLink($result);

      $children_markup = [];
      foreach ($children as $child) {
        $children_markup[] = $this->buildResultItem($child);
      }

      $classes[] = 'expanded';
      $items['children'] = [$children_markup];

      if ($result->isActive()) {
        $items['#attributes'] = ['class' => ['active-trail']];
      }
    }
    else {
      $items = $this->prepareLink($result);

      if ($result->isActive()) {
        $items['#attributes'] = ['class' => ['is-active']];
      }
    }

    $items['#wrapper_attributes'] = ['class' => $classes];
    $items['#attributes']['data-drupal-facet-item-id'] = $this->facet->getUrlAlias() . '-' . $result->getRawValue();

    if (in_array($result, $this->resultTabs)) {
      $items['#attributes']['class'][] = 'tab';
      $items['#attributes']['class'][] = 'tab--content-type';
      $items['#attributes']['role'][] = 'tab';
    }

    return $items;
  }

}
