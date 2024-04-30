<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides interface for "joinup_group.group_settings_form_alter" service.
 */
interface GroupSettingsFormAlterInterface {

  /**
   * Alter form settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function alterForm(array &$form, FormStateInterface $form_state): void;

}
