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
use Drupal\joinup_i18n\JoinupI18nHelper;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\tmgmt\Translator\ETranslationTranslator;

/**
 * Hook implementations for joinup_i18n.
 */
class JoinupI18nHooks {

  public function __construct(
    protected Connection $connection,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected JoinupI18nHelper $joinupI18nHelper,
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

    // Ensure our generic check is only for desired entity type IDs.
    if (!in_array($entity->getEntityTypeId(), array_keys($this->joinupI18nHelper::CONTINUOUS_SETTINGS['content']))) {
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

    // TMGMT forces the need to have the bundle set when checking if a job item
    // should be created.
    // @see \Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource::shouldCreateContinuousItem
    // Load all existing continuous jobs for this source language + translator
    // in one query for performance.
    $existingTargetLangcodes = $this->connection->select('tmgmt_job', 'tj')
      ->fields('tj', ['target_language'])
      ->condition('source_language', $sourceLangcode)
      ->condition('translator', ETranslationTranslator::ID)
      ->condition('job_type', JobInterface::TYPE_CONTINUOUS)
      ->execute()
      ->fetchCol();

    $jobStorage = $this->entityTypeManager->getStorage('tmgmt_job');
    $settings = $this->joinupI18nHelper->getContinuousSettings();
    foreach (array_diff($targetLangcodes, $existingTargetLangcodes) as $targetLangcode) {
      $jobStorage->create([
        'source_language' => $sourceLangcode,
        'target_language' => $targetLangcode,
        'label' => "Joinup: {$sourceLangcode} > {$targetLangcode}",
        'uid' => 0,
        'translator' => ETranslationTranslator::ID,
        'settings' => [],
        'state' => JobInterface::STATE_CONTINUOUS,
        'job_type' => JobInterface::TYPE_CONTINUOUS,
        'continuous_settings' => $settings,
      ])->save();
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for node_type entity type.
   */
  #[Hook('node_type_insert')]
  public function entityNodeTypeInsert(EntityInterface $entity): void {
    $this->updateContinuousJobsWithNodeBundles();
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for node_type entity type.
   */
  #[Hook('node_type_delete')]
  public function entityNodeTypeDelete(EntityInterface $entity): void {
    $this->updateContinuousJobsWithNodeBundles();
  }

  /**
   * Updates all continuous jobs with the existing node bundles.
   *
   * This updates the continuous jobs as needed based on the current set of
   * bundles available across all node types in the system.
   */
  private function updateContinuousJobsWithNodeBundles(): void {
    $continuousSettings = $this->joinupI18nHelper->getContinuousSettings();

    $jobStorage = $this->entityTypeManager->getStorage('tmgmt_job');
    $jobStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('job_type', JobInterface::TYPE_CONTINUOUS)
      ->condition('translator', ETranslationTranslator::ID)
      ->execute();

    foreach ($jobStorage->loadMultiple() as $job) {
      $job->set('continuous_settings', $continuousSettings);
      $job->save();
    }
  }

}
