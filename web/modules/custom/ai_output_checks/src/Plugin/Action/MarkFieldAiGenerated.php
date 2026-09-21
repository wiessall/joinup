<?php

namespace Drupal\ai_output_checks\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\ai_output_checks\Infrastructure\Drupal\QaParagraphManager;
use Drupal\Core\Utility\Token;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Marks a specific node field as AI-generated (via QA paragraph).
 *
 * @Action(
 *   id = "ai_output_checks_mark_field_ai_generated",
 *   label = @Translation("AI Output Checks: Mark field as AI-generated"),
 *   category = @Translation("AI Output Checks"),
 *   type = "node"
 * )
 */
final class MarkFieldAiGenerated extends ActionBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly QaParagraphManager $qaManager,
    private readonly LoggerInterface $logger,
    private readonly Token $token,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_output_checks.qa_paragraph_manager'),
      $container->get('logger.channel.ai_output_checks'),
      $container->get('token'),
    );
  }

  public function defaultConfiguration(): array {
    return [
      'field_machine_name' => '',
      'ai_generated' => TRUE,
      'replace_tokens' => TRUE,
    ] + parent::defaultConfiguration();
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_machine_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field machine name'),
      '#description' => $this->t('Example: field_url_s, field_description, title'),
      '#default_value' => (string) ($this->configuration['field_machine_name'] ?? ''),
      '#required' => TRUE,
    ];

    $form['ai_generated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set ai_generated = TRUE'),
      '#default_value' => (bool) ($this->configuration['ai_generated'] ?? TRUE),
    ];

    $form['replace_tokens'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace tokens in field machine name'),
      '#default_value' => (bool) ($this->configuration['replace_tokens'] ?? TRUE),
      '#description' => $this->t('Allows values like field_[some_token]. Most people leave this on.'),
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_machine_name'] = (string) $form_state->getValue('field_machine_name');
    $this->configuration['ai_generated'] = (bool) $form_state->getValue('ai_generated');
    $this->configuration['replace_tokens'] = (bool) $form_state->getValue('replace_tokens');
    parent::submitConfigurationForm($form, $form_state);
  }

  public function execute($entity = NULL): void {
    if (!$entity instanceof NodeInterface) {
      $this->logger->warning('MarkFieldAiGenerated: called without a node entity.');
      return;
    }

    $field = (string) ($this->configuration['field_machine_name'] ?? '');
    if (!empty($this->configuration['replace_tokens'])) {
      $field = $this->token->replace($field, ['node' => $entity], ['clear' => TRUE]);
    }
    $field = trim($field);

    if ($field === '') {
      $this->logger->warning('MarkFieldAiGenerated: empty field machine name.');
      return;
    }

    $flag = !empty($this->configuration['ai_generated']);

    // IMPORTANT: QaParagraphManager handles “create QA paragraph if missing”
    // and keeps paragraph revision references in sync.
    $this->qaManager->setAiGenerated($entity, $field, $flag);

    $this->logger->notice(
      'MarkFieldAiGenerated: set ai_generated=@flag for field @field on node @nid.',
      ['@flag' => $flag ? 'TRUE' : 'FALSE', '@field' => $field, '@nid' => $entity->id()]
    );
  }

  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = ($object instanceof NodeInterface)
      ? $object->access('update', $account, TRUE)
      : AccessResult::forbidden();

    return $return_as_object ? $result : $result->isAllowed();
  }

}
