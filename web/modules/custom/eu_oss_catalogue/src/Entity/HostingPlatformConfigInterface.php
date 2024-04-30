<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Interface for 'hosting_platform' entities.
 */
interface HostingPlatformConfigInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Returns the code hosting platform plugin ID.
   *
   * @return string
   *   The code hosting platform plugin ID.
   */
  public function getPlatformPluginId(): string;

  /**
   * Returns the code hosting platform plugin instance settings.
   *
   * @return array
   *   The code hosting platform plugin instance settings.
   */
  public function getPlatformSettings(): array;

  /**
   * Gets the description.
   *
   * @return string|null
   *   The description of this code hosting platform config.
   */
  public function getDescription(): ?string;

}
