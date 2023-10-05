<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\Model\Project;

/**
 * Provides an interface for value resolver plugins.
 */
interface EuOssCatalogueResolverInterface extends PluginInspectionInterface, ConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * Resolves a value imported by a provider.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The Drupal field item list instance.
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   * @param array $metadata
   *   The project metadata.
   *
   * @return mixed
   *   The value that has been resolved. Note that the value is only returned
   *   because it might have value for a plugin extending other plugin and using
   *   parent values. The $fieldItemList instance is already updated and should
   *   be used by the caller.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\UnresolvableValueException
   *   If the value cannot be resolved.
   */
  public function resolve(FieldItemListInterface $fieldItemList, Project $project, array $metadata): mixed;

}
