<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'ai_enhancement_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['ai_enhancement.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ai_enhancement.settings');

    $form['ollama_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Ollama base URL'),
      '#default_value' => $config->get('ollama_base_url') ?: 'http://localhost:11434',
      '#required' => TRUE,
    ];
    $form['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Request timeout (seconds)'),
      '#default_value' => $config->get('request_timeout') ?: 120,
      '#min' => 5,
      '#max' => 600,
    ];
    $form['max_tool_loop_iterations'] = [
      '#type' => 'number',
      '#title' => $this->t('Max tool-call loop iterations'),
      '#default_value' => $config->get('max_tool_loop_iterations') ?: 6,
      '#min' => 1,
      '#max' => 20,
      '#description' => $this->t('Safety cap on how many times the model may call tools in a single chat() call before giving up.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('ai_enhancement.settings')
      ->set('ollama_base_url', rtrim((string) $form_state->getValue('ollama_base_url'), '/'))
      ->set('request_timeout', (int) $form_state->getValue('request_timeout'))
      ->set('max_tool_loop_iterations', (int) $form_state->getValue('max_tool_loop_iterations'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
