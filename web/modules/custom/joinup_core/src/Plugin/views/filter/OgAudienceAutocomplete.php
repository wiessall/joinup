<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters by OG audience group using entity autocomplete.
 *
 * @ViewsFilter("og_audience_autocomplete")
 */
class OgAudienceAutocomplete extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Group'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#placeholder' => $this->t('Search for a group'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
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
  public function query() {
    $this->ensureMyTable();

    if (empty($this->value)) {
      return;
    }

    $this->query->addWhere(
      0,
      "{$this->table}.target_id",
      $this->value,
      '='
    );
  }

}
