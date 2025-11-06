<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\QueueWorker;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Validator\PublicCodeValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker to validate publiccode.yml of OSS solutions.
 */
class EuOssCatalogueValidator extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PublicCodeValidatorInterface $publicCodeValidator,
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $solution = $this->getOssSolution($data['id']);
    if (!$solution) {
      return;
    }

    // Validate.
    $publiccode = $solution->getPubliccodeYaml();
    assert(is_string($publiccode));
    $validationResult = $this->publicCodeValidator->validate($publiccode);

    // Save current validation result to the OSS solution object.
    $solution->setNewRevision(FALSE);
    $solution
      ->setSyncing(TRUE)
      ->setValidationResult($validationResult)
      ->save();
  }

  /**
   * Loads the OSS solution object.
   *
   * @param string|int $identifier
   *   The identifier of the OSS solution object.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface|null
   *   The OSS solution object, or NULL if it cannot be found.
   */
  protected function getOssSolution(string|int $identifier): ?OssSolutionInterface {
    static $ossSolutionStorage;
    if (!$ossSolutionStorage instanceof ContentEntityStorageInterface) {
      $ossSolutionStorage = $this->entityTypeManager->getStorage('node');
      \assert($ossSolutionStorage instanceof ContentEntityStorageInterface);
    }

    $solution = $ossSolutionStorage->load($identifier);
    if (!$solution) {
      return NULL;
    }
    \assert($solution instanceof OssSolutionInterface);
    return $solution;
  }

}
