<?php

declare(strict_types=1);

namespace Drupal\joinup_linkchecker\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters by group using entity autocomplete.
 */
#[ViewsFilter("joinup_linkchecker_group_autocomplete")]
class JoinupLinkCheckerGroupAutocomplete extends FilterPluginBase {

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
    $identifier = $this->options['expose']['identifier'] ?? 'group';

    if (!empty($input[$identifier])) {
      $this->value = (int) $input[$identifier];
      return $this->value > 0;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    if (!empty($this->value)) {
      $this->query->addWhere($this->options['group'], "$this->tableAlias.group", $this->value, '=');
    }
  }

}
