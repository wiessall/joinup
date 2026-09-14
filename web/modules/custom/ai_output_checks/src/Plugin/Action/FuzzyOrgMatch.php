<?php

namespace Drupal\ai_output_checks\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\ai_output_checks\Service\DuplicateDetector;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\ai_output_checks\Infrastructure\Drupal\QaParagraphManager;
use Drupal\ai_output_checks\Infrastructure\Drupal\NodeAudit;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fuzzy-duplicate check for organizations referenced on innovation nodes.
 *
 * @Action(
 *   id = "ai_output_checks_fuzzy_org_match",
 *   label = @Translation("AI Output Checks: Fuzzy org match (innovation)"),
 *   category = @Translation("AI Output Checks"),
 *   type = "node"
 * )
 */
final class FuzzyOrgMatch extends ActionBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly DuplicateDetector $duplicateDetector,
    private readonly QaParagraphManager $qaManager,
    private readonly LoggerInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly NodeAudit $audit,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_output_checks.duplicate_detector'),
      $container->get('ai_output_checks.qa_paragraph_manager'),
      $container->get('logger.channel.ai_output_checks'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('ai_output_checks.node_audit'),
    );
  }

  /**
   * Load all orgs that look like duplicates of the given name.
   */
  protected function findOrgMatchesForName(string $input): array {
    $input = trim($input);
    if ($input === '') {
      return [];
    }

    // Load all organizations (add 'status' => 1 if you only want published).
    $storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface[] $all_organizations */
    $all_organizations = $storage->loadByProperties([
      'type' => 'organization',
    // 'status' => 1,
    ]);

    // Configurable pre-filter threshold, default 80.
    $config = $this->configFactory->get('ai_output_checks.settings');
    $partialThreshold = (int) ($config->get('partial_ratio_threshold') ?? 80);

    $matches = [];

    foreach ($all_organizations as $organization) {
      if (!$organization instanceof NodeInterface) {
        continue;
      }

      $label = (string) $organization->label();
      if ($label === '') {
        continue;
      }

      // 1) PRE-FILTER using partial ratio.
      $partial = $this->duplicateDetector->partialRatio($input, $label);
      if ($partial < $partialThreshold) {
        continue;
      }

      // 2) HEAVIER check: your multi-metric DuplicateDetector.
      $is_dup = $this->duplicateDetector->isDuplicate($input, $label);
      if ($is_dup) {
        $matches[] = [
          'nid'     => $organization->id(),
          'title'   => $label,
          'partial' => $partial,
        ];
      }
    }

    return $matches;
  }

  /**
   * {@inheritdoc}
   *
   * ECA passes the *node* entity here (because type = "node").
   */
  public function execute($entity = NULL): void {
    if (!$entity instanceof NodeInterface) {
      $this->logger->warning(
        'FuzzyOrgMatch: called without a node entity.'
      );
      return;
    }

    if ($entity->bundle() === 'organization') {
      if ($this->qaManager->hasPositiveStatus($entity, 'title')) {
        return;
      }

      $name = trim((string) $entity->label());
      if ($name === '') {
        return;
      }

      $matches = $this->findOrgMatchesForName($name);
      $matches = array_values(array_filter($matches, fn($m) => (int) $m['nid'] !== (int) $entity->id()));

      if (!empty($matches)) {
        $this->qaManager->setStatusTerm($entity, 'title', 'Possible duplicate');
        if ($entity->hasField('field_state')) {
          $entity->set('field_state', 'Needs review');
        }
        $this->audit->appendLog($entity, 'eca/fuzzy_org_match', 'Possible duplicate organization title.');
        $entity->save();
      }

      return;
    }

    // Only act on innovation nodes.
    if ($entity->bundle() !== 'innovation') {
      return;
    }

    $detector = $this->duplicateDetector;

    // The innovation fields that contain org *names* as strings.
    $org_fields = [
      'field_owner',
      'field_sponsors',
      'field_partners',
      'field_owner_text_field',
      'field_sponsor_text_field',
      'field_partner_text_field',
    ];

    foreach ($org_fields as $org_field) {
      if ($this->qaManager->hasPositiveStatus($entity, $org_field)) {
        continue;
      }
      if (!$entity->hasField($org_field)) {
        continue;
      }

      $has_duplicate = FALSE;
      $all_matches   = [];

      // Collect all strings from this field (handles single & multi value).
      foreach ($entity->get($org_field) as $item) {
        $org_name = trim((string) ($item->value ?? ''));
        if ($org_name === '') {
          continue;
        }

        $matches = $this->findOrgMatchesForName($org_name);
        if (!empty($matches)) {
          $has_duplicate = TRUE;
          $all_matches[$org_name] = $matches;
        }
      }

      // If any name in this field had dupes, mark the QA paragraph + log.
      if ($has_duplicate) {

        $log_parts = [];
        foreach ($all_matches as $input => $matches) {
          $targets = array_map(
            fn(array $m) => sprintf(
              '%s (nid=%d, partial=%0.1f)',
              $m['title'],
              $m['nid'],
              $m['partial']
            ),
            $matches
          );
          $log_parts[] = sprintf('"%s" -> %s', $input, implode('; ', $targets));
        }


        // Set relevant *field* to possible duplicate
        $this->qaManager->setStatusTerm($entity, $org_field, 'Possible duplicate');

        if ($entity->hasField('field_state')) {
          $entity->set('field_state', 'Needs review');
        }
        $this->audit->appendLog($entity, 'eca/fuzzy_org_match', "Possible duplicate(s) in $org_field: " . implode(' | ', $log_parts));
        // $entity->save();

        
        $this->logger->notice(
          'FuzzyOrgMatch (innovation): duplicates for field @field on node @nid: @details',
          [
            '@field'   => $org_field,
            '@nid'     => $entity->id(),
            '@details' => implode(' | ', $log_parts),
          ]
        );
      }
      else {
        $this->logger->notice(
          'FuzzyOrgMatch (innovation): no duplicates found for field @field on node @nid.',
          [
            '@field' => $org_field,
            '@nid'   => $entity->id(),
          ]
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Allow users who can view the node to run this action.
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof NodeInterface) {
      $result = $object->access('view', $account, TRUE);
    }
    else {
      $result = AccessResult::forbidden();
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
