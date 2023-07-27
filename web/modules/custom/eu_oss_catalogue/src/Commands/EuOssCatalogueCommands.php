<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderUsingMigrateInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Drush commands.
 */
class EuOssCatalogueCommands extends DrushCommands {

  /**
   * Constructs a new Drush commands class instance.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $providerPluginManager
   *   The EU OSS Catalog provider plugin manager.
   */
  public function __construct(
    protected PluginManagerInterface $providerPluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  #[CLI\Command(name: 'eu-oss:fetch')]
  #[CLI\Argument(name: 'provider', description: 'The provider plugin ID')]
  public function fetch(string $provider) {
    $plugin = $this->providerPluginManager->createInstance($provider);
    if ($plugin instanceof EuOssCatalogueProviderUsingMigrateInterface) {
      $process = Drush::drush(
        Drush::aliasManager()->getSelf(),
        'migrate:import',
        [implode(',', $plugin->getMigrations())],
        [
          'update' => NULL,
          'execute-dependencies' => NULL,
          'delete' => NULL,
          'no-interaction' => TRUE,
        ],
      );
      $process->setTimeout(NULL)->run();
      return $process->getOutput();
    }
  }

  #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, selector: 'eu-oss:fetch')]
  public function validateProviderPluginId(CommandData $commandData): ?CommandError {
    $pluginId = $commandData->input()->getArgument('provider');
    if (!$this->providerPluginManager->hasDefinition($pluginId)) {
      return new CommandError(dt("The '@plugin' EU OSS Catalogue provider doesn't exist", [
        '@plugin' => $pluginId,
      ]));
    }
    return NULL;
  }

  #[CLI\Command(name: 'eu-oss:import-spdx')]
  public function importSpdxTerms(): void {
    $batchBuilder = new BatchBuilder();
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids = array_values($termStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'oss_licence')
      ->execute());
    if ($tids) {
      $total = count($tids);
      foreach (array_chunk($tids, 20) as $chunk) {
        $batchBuilder->addOperation([self::class, 'removeExistingTerms'], [$chunk, $total]);
      }
    }

    $licensesJsonFile = DRUPAL_ROOT . "/../vendor/spdx/license-list-data/json/licenses.json";
    $licenses = Json::decode(file_get_contents($licensesJsonFile))['licenses'];

    $total = count($licenses);
    foreach (array_chunk($licenses, 20) as $chunk) {
      $batchBuilder->addOperation([self::class, 'addLicenceTerms'], [$chunk, $total]);
    }
    batch_set($batchBuilder->toArray());
    drush_backend_batch_process();
  }

  public static function removeExistingTerms(array $tids, int $total, array &$context): void {
    if (!isset($context['results']['deleted'])) {
      $context['results']['deleted'] = 0;
    }
    $context['results']['deleted'] += count($tids);

    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $termStorage->delete($termStorage->loadMultiple($tids));

    Drush::logger()->notice(dt('Deleted @count out of @total existing licenses', [
      '@count' => $context['results']['deleted'],
      '@total' => $total,
    ]));
  }

  public static function addLicenceTerms(array $licences, int $total, array &$context): void {
    if (!isset($context['results']['added'])) {
      $context['results']['added'] = 0;
    }
    $context['results']['added'] += count($licences);
    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach ($licences as $licence) {
      $termStorage->create([
        'vid' => 'oss_licence',
        'tid' => $licence['reference'],
        'name' => $licence['licenseId'],
      ])->save();
    }

    Drush::logger()->notice(dt('Imported @count out of @total licenses', [
      '@count' => $context['results']['added'],
      '@total' => $total,
    ]));
  }

}
