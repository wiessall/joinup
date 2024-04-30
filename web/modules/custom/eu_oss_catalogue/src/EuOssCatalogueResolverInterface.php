<?php

declare(strict_types=1);

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
   * Multiple resolvers can run in cascade. The value of the `$value` parameter,
   * passed by reference, is altered sequentially by the downstream resolvers.
   *
   * The structure of `$value` parameter is important because the parameter is
   * language aware. The first resolver in the flow, will receive NULL in this
   * parameter, but the downstream resolvers will receive an associative array
   * with the following structure:
   * - Keys are language codes. For the default language the language key should
   *   always be 'x-default'.
   * - Values should be always arrays of field item values.
   *
   * Example of `$value` for a non-translatable field:
   * @code
   * [
   *   'x-default' => ['apple', 'pineapple', 'plum'],
   * ]
   * @endcode
   * Example of `$value` for a translatable field:
   * @code
   *  [
   *    'x-default' => ['apple', 'pineapple', 'plum'],
   *    'de' => ['Apfel', 'Ananas', 'Pflaume'],
   *    'it' => ['mela', 'ananas', 'prugna'],
   *  ]
   * @endcode
   *
   * @param array|null $value
   *   The value to be resolved. It might carry data resulted from a previous
   *   resolver or NULL, if this is the first acting resolver. See the above
   *   documentation to learn about the array structure.
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
