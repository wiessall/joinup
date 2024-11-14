<?php

declare(strict_types=1);

namespace Drupal\joinup_log\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Provides a filter for joinup log group.
 */
#[ViewsFilter("joinup_log_group")]
class JoinupLogGroup extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    $form['value'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->options['expose']['label'],
      '#description' => $this->options['expose']['description'],
      '#tags' => TRUE,
      '#maxlength' => 1024,
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['collection', 'solution'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return ['joinup_log_list'];
  }

}
