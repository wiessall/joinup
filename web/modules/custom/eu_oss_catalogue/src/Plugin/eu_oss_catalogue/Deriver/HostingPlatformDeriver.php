<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Entity\HostingPlatformConfigInterface;
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
        'description' => $this->getDerivativeDescription($base_plugin_definition, $hostingPlatform),
        'platformId' => $hostingPlatform->getPlatformPluginId(),
        'platformSettings' => $hostingPlatform->getPlatformSettings(),
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

  /**
   * Gets derivative description.
   *
   * @param array $basePluginDefinition
   *   Plugin definition.
   * @param \Drupal\eu_oss_catalogue\Entity\HostingPlatformConfigInterface $hostingPlatform
   *   Hosting platform.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Description.
   */
  protected function getDerivativeDescription(array $basePluginDefinition, HostingPlatformConfigInterface $hostingPlatform): TranslatableMarkup {
    $platformId = $hostingPlatform->getPlatformPluginId();

    /** @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection $settings */
    $settings = $hostingPlatform->getPluginCollections()['platformSettings'];

    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface $platform */
    $platform = $settings->get($platformId);

    if ($hostingPlatform->getDescription()) {
      $args = [
        '@description' => $hostingPlatform->getDescription() ?? '',
        '@platform' => $platform->getLabel(),
      ];
    }
    else {
      $args = [
        '@description' => $basePluginDefinition['description'],
        '@platform' => $platform->getLabel(),
      ];
    }

    return $this->t('@description (@platform)', $args);
  }

}
