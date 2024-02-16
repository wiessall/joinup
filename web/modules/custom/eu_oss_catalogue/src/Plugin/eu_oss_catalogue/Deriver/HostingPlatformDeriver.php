<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for the 'hosting_platform' provider plugin.
 */
class HostingPlatformDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new deriver instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): self {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $hostingPlatforms = $this->entityTypeManager->getStorage('hosting_platform')->loadMultiple();
    /** @var \Drupal\eu_oss_catalogue\Entity\HostingPlatformConfigInterface $hostingPlatform */
    foreach ($hostingPlatforms as $hostingPlatform) {
      $this->derivatives[$hostingPlatform->id()] = [
        'label' => $hostingPlatform->label(),
        'description' => $hostingPlatform->getDescription(),
        'platformId' => $hostingPlatform->getPlatformPluginId(),
        'platformSettings' => $hostingPlatform->getPlatformSettings(),
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
