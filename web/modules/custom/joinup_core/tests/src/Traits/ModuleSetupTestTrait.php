<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\Traits;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Trait for fully installing modules in PHPUnit kernel tests.
 */
trait ModuleSetupTestTrait {

  /**
   * Fully installs all enables crucial modules.
   */
  protected function setupKeyModules(): void {
    // Install the necessary modules.
    $this->fullyInstallModuleIfExists([
      'user',
      'system',
      'locale',
      'file',
      'image',
      'node',
      'taxonomy',
      'path_alias',
      'redirect',
      'paragraphs',
      'votingapi',
      'flag',
      'media',
      'views',
    ]);
  }

  /**
   * Fully installs the given module and its content entities if it is enabled.
   *
   * @param array|string $module
   *   The name of the module(s).
   */
  protected function fullyInstallModuleIfExists(array|string $module): void {
    foreach ((array) $module as $module_to_install) {
      if (!($module_handler = \Drupal::moduleHandler())->moduleExists($module_to_install)) {
        continue;
      }

      $installer_name = $module_handler->loadInclude($module_to_install, 'install');

      if ($installer_name && function_exists($schema_function = "{$module_to_install}_schema")) {
        $tables = $schema_function();
        if ($module_to_install === 'system') {
          // @see https://www.drupal.org/node/3349345
          unset($tables['sequences']);
        }
        $this->installSchema($module_to_install, array_keys($tables));
      }

      \Drupal::entityTypeManager()->clearCachedDefinitions();
      $content_entities_to_install = array_keys(
        array_filter(
          \Drupal::entityTypeManager()->getDefinitions(),
          function (EntityTypeInterface $entity_type) use ($module_to_install) {
            return $entity_type->getProvider() === $module_to_install &&
              $entity_type instanceof ContentEntityTypeInterface;
          }
        )
      );
      foreach ($content_entities_to_install as $entity_type_id) {
        $this->installEntitySchema($entity_type_id);
      }

      $this->installConfig([$module_to_install]);

      if ($installer_name && function_exists($install_function = "{$module_to_install}_install")) {
        $install_function(FALSE);
      }
    }
  }

}
