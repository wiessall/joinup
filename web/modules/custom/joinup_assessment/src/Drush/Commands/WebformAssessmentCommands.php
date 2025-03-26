<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\mysql\Driver\Database\mysql\Connection;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Commands\sql\sanitize\SanitizeCommands;
use Drush\Commands\sql\sanitize\SanitizePluginInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Overwrites Webform Drush sanitization plugins.
 *
 * @see \Drush\Commands\sql\sanitize\SanitizeCommands::sanitize()
 */
class WebformAssessmentCommands extends DrushCommands implements SanitizePluginInterface {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'database')]
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct();
  }

  /**
   * Disables default webform sanitization.
   *
   * @see \Drupal\webform\Commands\WebformSanitizeSubmissionsCommands::sanitize()
   */
  #[CLi\Argument(name: 'input', description: 'Input')]
  #[CLI\Hook(type: HookManager::INITIALIZE, target: 'sql-sanitize')]
  public function disableDefaultWebformSanitization(InputInterface $input): void {
    // @see \Drupal\webform\Commands\WebformSanitizeSubmissionsCommands::sanitize()
    $input->setOption('sanitize-webform-submissions', 'no');
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'sql-sanitize')]
  public function sanitize($result, CommandData $commandData): void {
    $webformStorage = $this->entityTypeManager->getStorage('webform_submission');
    $querySubmission = $webformStorage->getQuery()
      ->condition('webform_id', 'assessment', '!=')
      ->accessCheck(FALSE);

    do {
      $sids = $querySubmission->range(0, 50)->execute();

      if (!empty($sids)) {
        $webformStorage->delete(
          $webformStorage->loadMultiple($sids)
        );
      }
    } while (!empty($sids));

    if ($this->moduleHandler->moduleExists('webform_submission_log')) {
      $this->database->truncate('webform_submission_log')->execute();
    }

    $this->logger()
      ->success('Webform submission tables truncated except for the assessment.');
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Sanitize webform submission data except for the assessment.');
  }

}
