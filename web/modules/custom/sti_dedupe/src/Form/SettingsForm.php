<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings for duplicate detection.
 */
final class SettingsForm extends ConfigFormBase {

  private const SETTINGS = 'sti_dedupe.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sti_dedupe_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::SETTINGS);

    $form['threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Threshold'),
      '#description' => $this->t('Minimum score, between 0 and 1, for a pair to be offered for review.'),
      '#default_value' => $config->get('threshold'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
    ];

    $form['max_candidates'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum candidates per solution'),
      '#default_value' => $config->get('max_candidates'),
      '#min' => 1,
      '#max' => 20,
    ];

    $form['indexes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Search API indexes'),
      '#description' => $this->t('One index id per line. Newly imported solutions are usually unpublished, so include the unpublished index.'),
      '#default_value' => implode("\n", (array) $config->get('indexes')),
    ];

    $form['migration_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Migration tags'),
      '#description' => $this->t('One tag per line. The id maps of migrations carrying these tags are repointed when a merge deletes a node — without this, re-import recreates the duplicate.'),
      '#default_value' => implode("\n", (array) $config->get('migration_tags')),
    ];

    $form['compare_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Compared core fields'),
      '#description' => $this->t('One machine name per line. Claims are summarised rather than compared, so they do not belong here.'),
      '#default_value' => implode("\n", (array) $config->get('compare_fields')),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::SETTINGS)
      ->set('threshold', (float) $form_state->getValue('threshold'))
      ->set('max_candidates', (int) $form_state->getValue('max_candidates'))
      ->set('indexes', $this->lines($form_state->getValue('indexes')))
      ->set('migration_tags', $this->lines($form_state->getValue('migration_tags')))
      ->set('compare_fields', $this->lines($form_state->getValue('compare_fields')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Splits a textarea into a list of trimmed, non-empty lines.
   *
   * @return string[]
   *   The lines.
   */
  private function lines(?string $value): array {
    $lines = preg_split('/\R/', (string) $value) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
  }

}
