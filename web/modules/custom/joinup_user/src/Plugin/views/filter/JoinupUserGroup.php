<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\views\filter;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Provides a filter to filter users by group membership.
 */
#[ViewsFilter("joinup_user_group")]
class JoinupUserGroup extends FilterPluginBase {

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
  public function query(): void {
    $this->ensureMyTable();
    if (empty($this->value)) {
      return;
    }

    $subquery = Database::getConnection()->select('og_membership', 'ogm');
    $subquery->addField('ogm', 'uid');
    $subquery->condition('ogm.entity_type', 'node');
    $subquery->condition('ogm.entity_id', array_column($this->value, 'target_id'), 'IN');
    $this->query->addWhere($this->options['group'], "$this->tableAlias.uid", $subquery, 'IN');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return ['og_membership_list'];
  }

}
