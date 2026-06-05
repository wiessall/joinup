<?php

declare(strict_types=1);

namespace Drupal\joinup_media\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\StringArgument;

/**
 * View argument plugin for Media library to show only group related media.
 */
#[ViewsArgument("joinup_media_group_or_null")]
class JoinupMediaGroupOrNull extends StringArgument {

  /**
   * {@inheritdoc}
   */
  public function setArgument($arg): bool {
    if ($arg === $this->options['exception']['value_to_filter_for_null']) {
      $this->argument = FALSE;
      return TRUE;
    }
    return parent::setArgument($arg);
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE): void {
    if ($this->argument === FALSE && $this->is_default) {
      $this->ensureMyTable();
      $field = "$this->tableAlias.$this->realField";
      $this->query->addWhereExpression('0', "$field IS NULL");
      return;
    }
    parent::query($group_by);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['exception']['value_to_filter_for_null'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exception value to filter items having no value at this property'),
      '#size' => 20,
      '#default_value' => $this->options['exception']['value_to_filter_for_null'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['exception']['contains']['value_to_filter_for_null']['default'] = 'filter_empty';
    return $options;
  }

}
