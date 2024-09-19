<?php

declare(strict_types=1);

namespace Drupal\eu_oss_translation;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Job builder service.
 */
class JobBuilder implements JobBuilderInterface {

  /**
   * Target language.
   */
  public const string TARGET_LANGUAGE = 'en';

  /**
   * Module config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  public function __construct(
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelInterface $logger,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->config = $configFactory->get('eu_oss_translation.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function create(OssSolutionInterface $entity): void {
    if ($this->isBeingProcessed($entity)) {
      return;
    }

    if ($this->needsTranslation($entity)) {
      $this->createJob($entity);
    }
  }

  /**
   * Checks if entity being processed.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $entity
   *   Oss solution.
   *
   * @return bool
   *   True if it is being processed.
   */
  protected function isBeingProcessed(OssSolutionInterface $entity): bool {
    $sourceLangCode = $entity->getRelevantLangCode();

    return tmgmt_job_item_load_latest('content', $entity->getEntityTypeId(), $entity->id(), $sourceLangCode) !== FALSE;
  }

  /**
   * Checks if node needs translation.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $entity
   *   Oss solution.
   *
   * @return bool
   *   True if a node needs translation.
   */
  protected function needsTranslation(OssSolutionInterface $entity): bool {
    return $entity->getRelevantLangCode() !== self::TARGET_LANGUAGE;
  }

  /**
   * Creates tmgmt job.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $entity
   *   OSS entity.
   */
  protected function createJob(OssSolutionInterface $entity): void {
    try {
      $jobStorage = $this->entityTypeManager->getStorage('tmgmt_job');

      /** @var \Drupal\tmgmt\JobInterface $job */
      $job = $jobStorage->create(
        [
          'source_language' => $entity->getRelevantLangCode(),
          'target_language' => self::TARGET_LANGUAGE,
          'uid' => $this->currentUser->id(),
          'translator' => $this->config->get('translator'),
        ]
      );
      $job->addItem('content', $entity->getEntityTypeId(), $entity->id());

      $canRequest = $job->canRequestTranslation();
      if (!$canRequest->getSuccess()) {
        throw new \Exception((string) $canRequest->getReason());
      }

      $job->requestTranslation();
    }
    catch (\Exception $e) {
      $this->logger->error('An issue occurred during creating the translation job. Message: @message', [
        '@message' => $e->getMessage(),
        'oss_solution' => $entity->id(),
      ]);
    }
  }

}
