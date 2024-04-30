<?php

declare(strict_types=1);

namespace Drupal\joinup_sitewide_alert\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Sitewide alert drush commands.
 *
 * @see \Drush\Drupal\Commands\sql\SanitizeCommands::sanitize()
 */
class SitewideAlertCommands extends DrushCommands {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    public EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct();
  }

  /**
   * Unpublish Sitewide entity.
   *
   * @param array[] $options
   *   Command options.
   *
   * @command joinup:unpublish-alert
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
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
