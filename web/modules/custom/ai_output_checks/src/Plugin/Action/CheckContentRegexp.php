<?php

namespace Drupal\ai_output_checks\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\ai_output_checks\Infrastructure\Drupal\QaParagraphManager;
use Drupal\ai_output_checks\Application\Runner\CheckRunner;
use Drupal\ai_output_checks\Infrastructure\Drupal\NodeAudit;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate AI-generated node fields using regex & URL checks.
 * Clears failing field values; optionally deletes the node if AI title fails.
 *
 * @Action(
 *   id = "ai_output_checks_check_content_regexp",
 *   label = @Translation("AI Output Checks: Regex & URL guard (with AI labeling)"),
 *   category = @Translation("AI Output Checks"),
 *   type = "node"
 * )
 */
final class CheckContentRegexp extends ActionBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly CheckRunner $checkRunner,
    private readonly QaParagraphManager $qaManager,
    private readonly LoggerInterface $logger,
    private readonly NodeAudit $nodeAudit,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_output_checks.check_runner'),
      $container->get('ai_output_checks.qa_paragraph_manager'),
      $container->get('logger.channel.ai_output_checks'),
      $container->get('ai_output_checks.node_audit'),
    );
  }

  
  /**
   * Behavior toggles (easy to change later).
   * - validate_only_ai_marked: If TRUE, validate only fields that already have QA with ai_generated=TRUE.
   *   If FALSE, validate all eligible text/link fields on the node.
   */
  private bool $validateOnlyAiMarked = TRUE;

  /**
   * If TRUE: delete the node when AI title fails checks.
   * (Safe deletion relies on title QA ai_generated flag.)
   */
  private bool $deleteNodeOnBadAiTitle = TRUE;

  /**
   * Return fields to validate.
   *
   * If validateOnlyAiMarked: validate only those fields that are AI-marked by QA.
   * Else: validate all eligible text/link fields.
   */
  private function getFieldsToValidate(NodeInterface $node): array {
    if ($this->validateOnlyAiMarked) {
      // Only fields that the pipeline already marked as AI-generated.
      // Note: "title" might be included if you create QA row for it.
      return $this->qaManager->getAiMarkedFields($node);
    }

    // Otherwise: discover eligible text/link fields.
    $fields = ['title']; //! why does this only contain title

    foreach ($node->getFieldDefinitions() as $name => $def) {
      if ($def->isComputed()) {
        continue;
      }
      // Skip our QA field itself.
      if ($name === 'field_quality_assessment') {
        continue;
      }
      // Only configurable fields (skip base fields except body).
      // If you want base fields too, remove this check.
      // if (!$def instanceof \Drupal\field\Entity\FieldConfig && $name !== 'body') continue;

      if (!$node->hasField($name)) {
        continue;
      }

      // Accept only field types we can safely read/clear as strings.
      $type = $def->getType();
      $allowed = [
        'string',
        'string_long',
        'text',
        'text_long',
        'text_with_summary',
        'link',
      ];
      if (in_array($type, $allowed, TRUE)) {
        $fields[] = $name;
      }
    }

    return array_values(array_unique($fields));
  }

  private function extractValues(NodeInterface $node, string $fieldName): array {
    if ($fieldName === 'title') {
      $t = trim((string) $node->label());
      return $t !== '' ? [$t] : [];
    }

    if (!$node->hasField($fieldName)) {
      return [];
    }

    /** @var \Drupal\field\FieldItemListInterface $list */
    $list = $node->get($fieldName);

    $values = [];

    foreach ($list as $item) {
      // Link: uri
      if (isset($item->uri) && is_string($item->uri) && $item->uri !== '') {
        $values[] = trim($item->uri);
        continue;
      }
      // Text: value
      if (isset($item->value) && is_string($item->value) && $item->value !== '') {
        $values[] = trim($item->value);
        continue;
      }
    }

    return array_values(array_filter($values, fn($v) => $v !== ''));
  }

  private function clearField(NodeInterface $node, string $fieldName): void {
    if ($fieldName === 'title') {
      $node->setTitle('');
      return;
    }
    if ($node->hasField($fieldName)) {
      $node->set($fieldName, NULL);
    }
  }

  public function execute($entity = NULL): void {
    if (!$entity instanceof NodeInterface) {
      $this->logger->warning('RegexpGuard: called without a node entity.');
      return;
    }
    $this->logger->notice(
      'RegexpGuard: executing on node @nid bundle @bundle',
      ['@nid' => $entity->id(), '@bundle' => $entity->bundle()]
    );
    $fields = $this->getFieldsToValidate($entity);

    $this->logger->notice(
      'RegexpGuard: fieldsToValidate = @f',
      ['@f' => json_encode($fields)]
    );

    

    $changed = FALSE;

    foreach ($fields as $fieldName) {
      // Validate title even though it's not a "field".
      if ($fieldName !== 'title' && !$entity->hasField($fieldName)) {
        continue;
      }
      // Avoid fields that have already been set to 'reviewed'
      if ($this->qaManager->hasPositiveStatus($entity, $fieldName)) {
        continue;
      }


      $values = $this->extractValues($entity, $fieldName);
      if (!$values) {
        continue;
      }

      $fieldType = ($fieldName === 'title')
        ? 'string'
        : $entity->getFieldDefinition($fieldName)->getType();

      $report = $this->checkRunner->run($fieldName, $fieldType, $values);

      if ($report->isOk()) {
	 $this->qaManager->setAiGenerated($entity, $fieldName, TRUE);
	 // Valeria: Set a positive QA status and save?
         $this->qaManager->setStatusTerm($entity, $fieldName, 'Good');
         $changed = TRUE;
      }
      else {
        // $this->clearField($entity, $fieldName);
        $this->qaManager->setStatusTerm($entity, $fieldName, 'Definite hallucination');
        $this->nodeAudit->setRejectedBadAi($entity);
        $this->nodeAudit->setNeedsReview($entity);
        $changed = TRUE;
        $message = sprintf(
          'RegexpGuard: field %s on node %d failed checks; cleared. Findings: %s',
          $fieldName,
          $entity->id(),
          json_encode(array_map(fn($f) => [
            'check' => $f->checkId,
            'severity' => $f->severity,
            'message' => $f->message,
            'evidence' => $f->evidence,
            'data' => $f->data,
          ], $report->findings()))
        );
        $this->nodeAudit->appendLog($entity, 'Regexp or URL Check', $message); 
        $this->logger->notice(
          $message
        );
      }
    }
    if ($changed) {
      $entity->save();
    }
  }

  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = ($object instanceof NodeInterface)
      ? $object->access('update', $account, TRUE)
      : AccessResult::forbidden();

    return $return_as_object ? $result : $result->isAllowed();
  }

}
