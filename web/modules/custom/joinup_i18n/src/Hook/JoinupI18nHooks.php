<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderBefore;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\tmgmt\JobInterface;

/**
 * Hook implementations for joinup_i18n.
 */
class JoinupI18nHooks {

  protected const array CONTINUOUS_SETTINGS = [
    'content' => [
      'node' => ['enabled' => 1],
      'poll' => ['enabled' => 1],
      'poll_choice' => ['enabled' => 1],
    ],
  ];

  public function __construct(
    protected Connection $connection,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert', order: new OrderBefore(['tmgmt_content']))]
  public function entityInsert(EntityInterface $entity): void {
    $this->ensureContinuousJobs($entity);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update', order: new OrderBefore(['tmgmt_content']))]
  public function entityUpdate(EntityInterface $entity): void {
    $this->ensureContinuousJobs($entity);
  }

  /**
   * Creates missing ec_etranslation continuous jobs for an entity.
   *
   * For each enabled target language that has no continuous job covering the
   * entity's source language, a new continuous job is created so that
   * tmgmt_content can immediately attach job items to it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was inserted or updated.
   */
  public function ensureContinuousJobs(EntityInterface $entity): void {
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    if (!$entity->isTranslatable() || $entity->getEntityType()->get('entity_revision_parent_type_field') || !$entity->isDefaultTranslation()) {
      return;
    }

    $sourceLangcode = $entity->language()->getId();
    $targetLangcodeArray = $this->languageManager->getLanguages();
    $targetLangcodes = array_filter(
      array_keys($targetLangcodeArray),
      fn(string $langcode): bool => $langcode !== $sourceLangcode,
    );

    if (empty($targetLangcodes)) {
      return;
    }

    // Load all existing continuous jobs for this source language + translator
    // in one query for performance.
    $existingTargetLangcodes = $this->connection->select('tmgmt_job', 'tj')
      ->fields('tj', ['target_language'])
      ->condition('source_language', $sourceLangcode)
      ->condition('translator', 'ec_etranslation')
      ->condition('job_type', JobInterface::TYPE_CONTINUOUS)
      ->execute()
      ->fetchCol();

    $jobStorage = $this->entityTypeManager->getStorage('tmgmt_job');
    foreach (array_diff($targetLangcodes, $existingTargetLangcodes) as $targetLangcode) {
      $jobStorage->create([
        'source_language' => $sourceLangcode,
        'target_language' => $targetLangcode,
        'label' => "Joinup: {$sourceLangcode} > {$targetLangcode}",
        'uid' => 0,
        'translator' => 'ec_etranslation',
        'settings' => [],
        'state' => JobInterface::STATE_CONTINUOUS,
        'job_type' => JobInterface::TYPE_CONTINUOUS,
        'continuous_settings' => self::CONTINUOUS_SETTINGS,
      ])->save();
    }
  }

}
