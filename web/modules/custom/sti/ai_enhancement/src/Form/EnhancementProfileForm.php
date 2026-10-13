<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add/edit form for enhancement profiles.
 *
 * Intentionally minimal: textareas + simple inputs. A richer UI (autocomplete
 * for fields, model picker pulling from Ollama /api/tags, etc.) is on the
 * Phase-2 backlog — out of scope for the spike.
 */
class EnhancementProfileForm extends EntityForm {

  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\ai_enhancement\Entity\EnhancementProfileInterface $profile */
    $profile = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $profile->label() ?? '',
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $profile->id(),
      '#machine_name' => ['exists' => '\Drupal\ai_enhancement\Entity\EnhancementProfile::load'],
      '#disabled' => !$profile->isNew(),
    ];

    $form['target'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Target'),
    ];
    $form['target']['entity_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity type'),
      '#default_value' => $profile->get('entity_type') ?: 'node',
      '#required' => TRUE,
      '#description' => $this->t('Machine name, e.g. "node".'),
    ];
    $form['target']['bundle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bundle'),
      '#default_value' => $profile->get('bundle') ?: '',
      '#required' => TRUE,
    ];

    $form['fields'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fields'),
    ];
    $form['fields']['source_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Source fields'),
      '#default_value' => implode("\n", $profile->get('source_fields') ?: []),
      '#description' => $this->t('One field machine name per line. These feed the generator.'),
      '#rows' => 4,
    ];
    $form['fields']['target_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target annotation field'),
      '#default_value' => $profile->get('target_field') ?: '',
      '#required' => TRUE,
      '#description' => $this->t('Machine name of the field to write the AI annotation into. AI marking is handled via the QA paragraph (ai_output_checks).'),
    ];

    $form['models'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Models'),
    ];
    $form['models']['generator_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Generator model'),
      '#default_value' => $profile->get('generator_model') ?: 'llama3.1:8b',
      '#required' => TRUE,
    ];
    $form['models']['judge_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Judge model'),
      '#default_value' => $profile->get('judge_model') ?: 'llama3.1:8b',
      '#required' => TRUE,
    ];
    $form['models']['max_iterations'] = [
      '#type' => 'number',
      '#title' => $this->t('Max iterations'),
      '#default_value' => $profile->get('max_iterations') ?: 3,
      '#min' => 1,
      '#max' => 10,
    ];

    $form['prompts'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Prompts'),
    ];
    $form['prompts']['generator_system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Generator system prompt'),
      '#default_value' => $profile->get('generator_system_prompt') ?: '',
      '#rows' => 6,
    ];
    $form['prompts']['judge_system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Judge system prompt'),
      '#default_value' => $profile->get('judge_system_prompt') ?: '',
      '#rows' => 6,
      '#description' => $this->t('The judge returns structured JSON: {approved, feedback, issues[]}. Tell it what "approved" means for your domain.'),
    ];
    $form['prompts']['refinement_prompt_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Refinement prompt template'),
      '#default_value' => $profile->get('refinement_prompt_template') ?: '',
      '#rows' => 5,
      '#description' => $this->t('Available placeholders: {feedback}, {issues}, {report}. Leave blank for the default.'),
    ];

    $form['tools'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Tools'),
    ];
    $form['tools']['enabled_tools'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enabled tools'),
      '#default_value' => implode("\n", $profile->get('enabled_tools') ?: ['taxonomy_lookup']),
      '#rows' => 3,
      '#description' => $this->t('One tool name per line. Available: <code>taxonomy_lookup</code>. The deterministic check is not a tool — it runs as a separate pipeline step via ai_output_checks.'),
    ];

    return $form;
  }

  /**
   * Convert textarea strings to arrays before EntityForm maps them to the
   * typed array properties on EnhancementProfile. Without this, PHP 8.1
   * throws a TypeError when ConfigEntityBase::set() assigns a string to
   * a property declared as array.
   */
  protected function copyFormValuesToEntity(\Drupal\Core\Entity\EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    foreach (['source_fields', 'enabled_tools'] as $key) {
      $value = $form_state->getValue($key, '');
      $lines = is_array($value)
        ? array_values(array_filter(array_map('trim', $value)))
        : array_values(array_filter(array_map('trim', explode("\n", (string) $value))));
      $form_state->setValue($key, $lines);
    }
    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

  public function save(array $form, FormStateInterface $form_state): int {
    $status = $this->entity->save();
    $this->messenger()->addStatus($this->t('Saved profile %label.', ['%label' => $this->entity->label()]));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }

}
