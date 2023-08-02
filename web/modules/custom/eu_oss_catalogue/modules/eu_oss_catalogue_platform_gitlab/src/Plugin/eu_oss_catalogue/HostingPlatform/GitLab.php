<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_platform_gitlab\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformPluginBase;
use Drupal\eu_oss_catalogue\HostingPlatformResultInterface;

/**
 * Git code hosting platform for GitLab repositories.
 *
 * @EuOssCatalogueHostingPlatform(
 *   id = "gitlab",
 *   label = @Translation("GitLab"),
 * )
 */
class GitLab extends EuOssCatalogueHostingPlatformPluginBase implements PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $formState): void {
  }

  /**
   * {@inheritdoc}
   */
  public function getRepositories(): iterable {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function downloadFile(string $filePath, HostingPlatformResultInterface $result): ?string {
    return NULL;
  }

}
