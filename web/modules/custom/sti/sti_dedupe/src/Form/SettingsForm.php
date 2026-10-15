<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for duplicate detection and claim conflicts.
 */
final class SettingsForm extends ConfigFormBase {

  private const SETTINGS = 'sti_dedupe.settings';

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('sti_dedupe.exclusive_properties'),
      $container->get('sti_dedupe.conflict_queue'),
    );
  }

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

    $form['candidates'] = [
      '#type' => 'details',
      '#title' => $this->t('Duplicate candidates'),
      '#open' => TRUE,
    ];
    $form['candidates']['threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Threshold'),
      '#description' => $this->t('Minimum score, between 0 and 1, for a pair to be offered for review.'),
      '#default_value' => $config->get('threshold'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
    ];
    $form['candidates']['max_candidates'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum candidates per solution'),
      '#default_value' => $config->get('max_candidates'),
      '#min' => 1,
      '#max' => 20,
    ];
    $form['candidates']['indexes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Search API indexes'),
      '#description' => $this->t('One index id per line. Newly imported solutions are usually unpublished, so include the unpublished index.'),
      '#default_value' => implode("\n", (array) $config->get('indexes')),
    ];
    $form['candidates']['compare_fields'] = [
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
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['exclusive_add', 'exclusive_remove'] as $name) {
      foreach ($this->lines($form_state->getValue($name)) as $line) {
        if (filter_var($line, FILTER_VALIDATE_URL) === FALSE) {
          $form_state->setErrorByName($name, $this->t('"@line" is not a URI.', ['@line' => $line]));
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config(self::SETTINGS);
    $add = $this->lines($form_state->getValue('exclusive_add'));
    $remove = $this->lines($form_state->getValue('exclusive_remove'));
    $rulesChanged = $add !== (array) $config->get('exclusive_properties.add')
      || $remove !== (array) $config->get('exclusive_properties.remove');

    $config
      ->set('threshold', (float) $form_state->getValue('threshold'))
      ->set('max_candidates', (int) $form_state->getValue('max_candidates'))
      ->set('indexes', $this->lines($form_state->getValue('indexes')))
      ->set('compare_fields', $this->lines($form_state->getValue('compare_fields')))
      ->set('exclusive_properties.add', $add)
      ->set('exclusive_properties.remove', $remove)
      ->save();

    if ($rulesChanged) {
      $this->exclusive->reset();
      $this->conflictQueue->queueAll();
      $this->messenger()->addStatus($this->t('Claim conflicts will be recomputed for every innovation on the next cron runs.'));
    }
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
