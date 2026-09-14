<?php

namespace Drupal\ai_output_checks\Plugin\Action;

use Drupal\eca\Plugin\Action\ActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;
use Drupal\ai_output_checks\Infrastructure\Drupal\QaParagraphManager;
use Drupal\Core\Utility\Token;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Marks a specific node field as AI-generated (ECA-native).
 *
 * @EcaAction(
 *   id = "ai_output_checks_mark_field_ai_generated_eca",
 *   label = @Translation("AI Output Checks: Mark field as AI-generated (ECA)"),
 *   category = @Translation("AI Output Checks"),
 *   type = "node"
 * )
 */
final class MarkFieldAiGeneratedEca extends ActionBase implements ContainerFactoryPluginInterface {

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

  /**
   * IMPORTANT: make the signature match \Drupal\eca\Plugin\Action\ActionBase::create()
   * in *your* installed ECA version (return type may be ActionBase or static).
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)/* : static */ {
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
    // Let ECA add its own stuff first (token hints, etc.). :contentReference[oaicite:1]{index=1}
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['field_machine_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field machine name'),
      '#description' => $this->t('Example: field_url_s, field_description, title'),
      '#default_value' => (string) ($this->configuration['field_machine_name'] ?? ''),
      '#required' => TRUE,
      // Optional if you want ECA to flag token-capable fields:
      // '#eca_token_replacement' => TRUE,
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
      '#description' => $this->t('Allows values like field_[some_token].'),
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_machine_name'] = (string) $form_state->getValue('field_machine_name');
    $this->configuration['ai_generated'] = (bool) $form_state->getValue('ai_generated');
    $this->configuration['replace_tokens'] = (bool) $form_state->getValue('replace_tokens');

    parent::submitConfigurationForm($form, $form_state);
  }

  public function execute($entity = NULL): void {
    if (!$entity instanceof NodeInterface) {
      $this->logger->warning('MarkFieldAiGeneratedEca: called without a node entity.');
      return;
    }

    $field = (string) ($this->configuration['field_machine_name'] ?? '');
    if (!empty($this->configuration['replace_tokens'])) {
      $field = $this->token->replace($field, ['node' => $entity], ['clear' => TRUE]);
    }
    $field = trim($field);

    if ($field === '') {
      $this->logger->warning('MarkFieldAiGeneratedEca: empty field machine name.');
      return;
    }

    $flag = !empty($this->configuration['ai_generated']);

    $this->qaManager->setAiGenerated($entity, $field, $flag);

    $this->logger->notice(
      'MarkFieldAiGeneratedEca: set ai_generated=@flag for field @field on node @nid.',
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
