<?php

declare(strict_types=1);

namespace Drupal\joinup_sitewide_alert\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Sitewide alert drush commands.
 *
 * @see \Drush\Commands\sql\sanitize\SanitizeCommands::sanitize()
 */
class SitewideAlertCommands extends DrushCommands {

  public function __construct(
    public readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Unpublish Sitewide entity.
   */
  #[Cli\Command(name: 'joinup:unpublish-alert')]
  #[CLI\Option(name: 'category', description: 'Command options.')]
  public function unpublishAlert(array $options = ['category' => NULL]): void {
    $storage = $this->entityTypeManager->getStorage('sitewide_alert');

    $query = $storage->getQuery()
      ->condition('status', 1)
      ->accessCheck(FALSE);
    if (empty($options['category'])) {
      $query->condition('category', $options['category']);
    }

    $results = $query->execute();
    if (empty($results)) {
      $this->output()
        ->writeln('There are no entities which can be unpublished');
      return;
    }

    /** @var \Drupal\sitewide_alert\Entity\SitewideAlertInterface[] $alerts */
    $alerts = $storage->loadMultiple($results);
    foreach ($alerts as $alert) {
      $alert->setUnpublished();
      $alert->save();

      $this->output()->writeln(
        dt('Alert "@label" has been unpublished.', ['@label' => $alert->label()])
      );
    }

  }

}
