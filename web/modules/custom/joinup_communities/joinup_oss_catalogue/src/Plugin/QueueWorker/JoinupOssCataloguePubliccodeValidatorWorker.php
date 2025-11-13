<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\QueueWorker;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
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
    if (!is_string($publiccode)) {
      $this->logger->warning(
        "OSS solution validation was skipped for OSS solution @solution (@url): publiccode.yml field was empty",
        [
          '@solution' => $solution->label(),
          '@url' => $solution->toUrl()->toString(),
        ],
      );
      return;
    }
    $validationResult = $this->publicCodeValidator->validate($publiccode);

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
    static $ossSolutionStorage;
    if (!$ossSolutionStorage instanceof ContentEntityStorageInterface) {
      $ossSolutionStorage = $this->entityTypeManager->getStorage('node');
      \assert($ossSolutionStorage instanceof ContentEntityStorageInterface);
    }

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

    $solution = $ossSolutionStorage->load($data);
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
