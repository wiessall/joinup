<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters by OG audience group using entity autocomplete.
 */
#[ViewsFilter("og_audience_autocomplete")]
class OgAudienceAutocomplete extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function canExpose(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    $form['value'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Group'),
      '#target_type' => 'node',
      '#selection_handler' => 'default:node',
      '#selection_settings' => [
        'target_bundles' => ['collection', 'solution'],
      ],
      '#placeholder' => $this->t('Search for a group'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input): bool {
    $identifier = $this->options['expose']['identifier']
      ?? 'og_audience_target_id';

    if (empty($input[$identifier])) {
      return FALSE;
    }

    $this->value = (int) $input[$identifier];

    return $this->value > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();

    if (empty($this->value)) {
      return;
    }

    $this->query->addWhere(
      $this->options['group'],
      "$this->tableAlias.og_audience_target_id",
      $this->value, '='
    );
  }

}
