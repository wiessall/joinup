<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Plugin\QueueWorker;

use Drupal\ai_enhancement\Entity\EnhancementProfileInterface;
use Drupal\ai_enhancement\Service\EnhancementPipeline;
use Drupal\ai_output_checks\Infrastructure\Drupal\QaParagraphManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker plugin: processes one enhancement job.
 *
 * Job payload:
 *   [
 *     'entity_type' => 'node',
 *     'entity_id' => 123,
 *     'profile_id' => 'sti_project_enhancement',
 *   ]
 *
 * Set the cron time limit appropriately — each job may take 30s+ depending on
 * model and iteration count.
 */
#[\Drupal\Core\Queue\Attribute\QueueWorker(
  id: 'ai_enhancement_worker',
  title: new \Drupal\Core\StringTranslation\TranslatableMarkup('AI Enhancement worker'),
  cron: ['time' => 90],
)]
class EnhancementWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EnhancementPipeline $pipeline,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QaParagraphManager $qaParagraphManager,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_enhancement.pipeline'),
      $container->get('entity_type.manager'),
      $container->get('ai_output_checks.qa_paragraph_manager'),
      $container->get('logger.channel.ai_enhancement'),
    );
  }

  public function processItem($data): void {
    $entityType = $data['entity_type'] ?? '';
    $entityId = $data['entity_id'] ?? NULL;
    $profileId = $data['profile_id'] ?? '';

    if (!$entityType || !$entityId || !$profileId) {
      $this->logger->error('Malformed job payload: @p', ['@p' => print_r($data, TRUE)]);
      return;
    }

    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
    if (!$entity instanceof FieldableEntityInterface) {
      $this->logger->warning('Entity @t:@id not found or not fieldable.', ['@t' => $entityType, '@id' => $entityId]);
      return;
    }

    $profile = $this->entityTypeManager->getStorage('enhancement_profile')->load($profileId);
    if (!$profile instanceof EnhancementProfileInterface) {
      $this->logger->error('Profile @id not found.', ['@id' => $profileId]);
      return;
    }

    $result = $this->pipeline->run($entity, $profile);

    $this->logger->info('Enhanced @t:@id with profile @p — status=@s, iterations=@i', [
      '@t' => $entityType,
      '@id' => $entityId,
      '@p' => $profileId,
      '@s' => $result->status,
      '@i' => $result->iterations,
    ]);

    // Write back only on approval. Other statuses leave the entity untouched
    // so a human can review what happened in the logs.
    if ($result->isApproved()) {
      $this->writeBack($entity, $profile, $result->content);
    }
  }

  private function writeBack(FieldableEntityInterface $entity, EnhancementProfileInterface $profile, string $content): void {
    $targetField = $profile->getTargetField();
    if ($targetField && $entity->hasField($targetField)) {
      $entity->set($targetField, $content);
    }
    $entity->save();

    // Mark this field as AI-generated via the QA paragraph mechanism.
    // QaParagraphManager handles paragraph revision sync and only works on
    // nodes — non-node entities just get the field updated above.
    if ($entity instanceof NodeInterface && $targetField) {
      $this->qaParagraphManager->setAiGenerated($entity, $targetField, TRUE);
    }
  }

}
