<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Stores the configuration of a 'hosting_platform' plugin instance.
 *
 * @ConfigEntityType(
 *   id = "hosting_platform",
 *   label = @Translation("Code hosting platform config"),
 *   handlers = {
 *     "form" = {
 *       "edit" = "Drupal\eu_oss_catalogue\Form\HostingPlatformConfigForm",
 *       "delete" = "Drupal\eu_oss_catalogue\Form\HostingPlatformConfigDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\Core\Config\Entity\ConfigEntityListBuilder",
 *   },
 *   admin_permission = "administer eu oss catalogue",
 *   config_prefix = "hosting_platform",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/content/eu-oss-catalogue/hosting-platform/manage/{hosting_platform}",
 *     "delete-form" = "/admin/content/eu-oss-catalogue/hosting-platform/manage/{hosting_platform}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "platformId",
 *     "platformSettings",
 *   },
 * )
 */
class HostingPlatformConfig extends ConfigEntityBase implements HostingPlatformConfigInterface {

  /**
   * Entity ID.
   */
  protected string $id;

  /**
   * A meaningful label.
   */
  protected string $label;

  /**
   * The description.
   */
  protected ?string $description = NULL;

  /**
   * The code hosting platform plugin ID.
   */
  protected string $platformId;

  /**
   * The code hosting platform plugin instance settings.
   */
  protected array $platformSettings = [];

  /**
   * The code platform lazy plugin collection.
   */
  protected DefaultSingleLazyPluginCollection $platform;

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlatformPluginId(): string {
    return $this->platformId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlatformSettings(): array {
    return $this->platformSettings;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return [
      'platformSettings' => isset($this->platformId) ? $this->getPlatform() : NULL,
    ];
  }

  /**
   * Returns the code hosting platform plugin collection.
   *
   * @return \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   *   The code hosting platform plugin collection.
   */
  protected function getPlatform(): DefaultSingleLazyPluginCollection {
    if (!isset($this->platform)) {
      $platformManager = \Drupal::getContainer()->get('plugin.manager.eu_oss_catalogue.hosting_platform');
      $this->platform = new DefaultSingleLazyPluginCollection($platformManager, $this->platformId, $this->platformSettings);
    }
    return $this->platform;
  }

}
