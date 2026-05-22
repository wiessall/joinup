<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\facets\processor;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\processor\DisplayValueWidgetOrderProcessor;
use Drupal\facets\Processor\SortProcessorInterface;
use Drupal\facets\Result\Result;

/**
 * A processor that orders the results by display value.
 *
 * @FacetsProcessor(
 *   id = "oss_display_value_widget_order",
 *   label = @Translation("Sort oss sources by display value"),
 *   description = @Translation("Sorts the widget oss sources by display value."),
 *   default_enabled = FALSE,
 *   stages = {
 *     "sort" = 40
 *   }
 * )
 */
class OssDisplayValueWidgetOrderProcessor extends DisplayValueWidgetOrderProcessor implements SortProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function sortResults(Result $a, Result $b): int {
    // Get the transliterate values only once.
    if (!$a->get('transliterateDisplayValue')) {
      $a->set('transliterateDisplayValue', $this->transliteration->removeDiacritics($a->getDisplayValue()));
    }
    if (!$b->get('transliterateDisplayValue')) {
      $b->set('transliterateDisplayValue', $this->transliteration->removeDiacritics($b->getDisplayValue()));
    }

    // Return the sort value.
    if ($a->get('transliterateDisplayValue') == $b->get('transliterateDisplayValue')) {
      return 0;
    }
    // Always push "custom:other" to the end.
    if ($a->getRawValue() === 'custom:other') {
      return 1;
    }
    if ($b->getRawValue() === 'custom:other') {
      return -1;
    }

    return strnatcasecmp($a->get('transliterateDisplayValue'), $b->get('transliterateDisplayValue'));
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet): bool {
    return $facet->id() === 'oss_source';
  }

}
