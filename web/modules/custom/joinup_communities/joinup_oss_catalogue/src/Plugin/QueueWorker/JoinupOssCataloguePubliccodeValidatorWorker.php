<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\QueueWorker;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Validator\PublicCodeValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker to validate publiccode.yml of OSS solutions.
 *
 * This queue is used only once, at the initial deployment of the validation.
 */
#[QueueWorker(
  id: 'joinup_oss_catalogue_publiccode_validator',
  title: new TranslatableMarkup('Joinup OSS Catalogue validator for publiccode.yml'),
  cron: ['time' => 60],
)]
class JoinupOssCataloguePubliccodeValidatorWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PublicCodeValidatorInterface $publicCodeValidator,
    protected readonly LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('eu_oss_catalogue.publiccode_validator'),
      $container->get('logger.channel.eu_oss_catalogue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(mixed $data): void {
    $solution = $this->getOssSolution($data);
    if (!$solution) {
      return;
    }

    // Validate.
    $publiccode = $solution->getPublicCodeYaml();
    $validationResult = $this->publicCodeValidator->validate($publiccode);

    // Make sure that the publiccode.yml of the current OSS solution wasn't
    // updated since we started the validation. If it was updated, we re-queue
    // the current item.
    if ($publiccode !== $this->getOssSolution($data)->getPublicCodeYaml()) {
      throw new DelayedRequeueException();
    }
    // Save current validation result to the OSS solution object.
    $solution->setNewRevision(FALSE);
    $solution->skip_notification = TRUE;
    try {
      $solution
        ->setSyncing(TRUE)
        ->setValidationResult($validationResult)
        ->save();
    }
    catch (\Exception $e) {
      $this->logger->error(
        "Exception when trying to save validation results to solution '@solution' (@url): @error",
        [
          '@solution' => $solution->label(),
          '@url' => $solution->toUrl()->toString(),
          '@error' => $e->getMessage(),
        ],
      );
    }
  }

  /**
   * Loads the OSS solution object.
   *
   * @param mixed $data
   *   The queue item data, containing the OSS solution ID at the "solution_id"
   *   key.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface|null
   *   The OSS solution object, or NULL if it cannot be found.
   */
  protected function getOssSolution(mixed $data): ?OssSolutionInterface {
    if (
      empty($data) ||
      intval((string) $data) !== (int) $data
    ) {
      $this->logger->warning(
        "OSS solution validation queue item was invalid: @data",
        [
          '@data' => Variable::export($data),
        ],
      );
      return NULL;
    }

    // Always load the actual active revision, avoiding cache.
    $solution = $this->entityTypeManager->getStorage('node')->loadUnchanged($data);
    if (!$solution instanceof OssSolutionInterface) {
      $this->logger->warning(
        "OSS Solution with ID @id cannot be found",
        [
          '@id' => $data,
        ],
      );
      return NULL;
    }
    return $solution;
  }

}
