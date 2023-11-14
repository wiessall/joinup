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
   * @param array|null $value
   *   The value to be resolved. It might carry data resulted from a previous
   *   resolver or NULL, if this is the first acting resolver.
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   * @param array $metadata
   *   The project metadata.
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The Drupal field item list instance. The method might need information
   *   extracted form the field.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\UnresolvableValueException
   *   If the value cannot be resolved.
   */
  public function resolve(?array &$value, Project $project, array $metadata, FieldItemListInterface $fieldItemList): void;

}
