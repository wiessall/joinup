<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Context for update tests.
 */
class UpdateContext extends RawDrupalContext {

  /**
   * Ensures that update-related features use proper tags.
   *
   * @BeforeScenario @group-clone
   */
  public function checkUpdateExists(BeforeScenarioScope $scope): void {
    $update_tests_path = realpath(DRUPAL_ROOT . '/../tests/features/update');
    $feature_file = $scope->getFeature()->getFile();
    if (!str_starts_with($feature_file, $update_tests_path)) {
      // Only handle features inside tests/features/update.
      return;
    }

    $tags = array_unique(array_merge($scope->getFeature()->getTags(), $scope->getScenario()->getTags()));
    $updates = array_filter($tags, function (string $tag): bool {
      return str_starts_with($tag, 'update:');
    });

    if (empty($updates)) {
      $path = sprintf('%s:%d', $scope->getFeature()->getFile(), $scope->getScenario()->getLine());
      throw new \Exception("$path\n\tScenarios inside $update_tests_path should always be tagged with @update:[function], where [function] is the update/post-update/deploy function.");
    }

    $missing_updates = [];
    foreach ($updates as $tag) {
      [, $update] = explode(':', $tag, 2);
      if (!$this->loadUpdateFile($update) || !function_exists($update)) {
        $missing_updates[] = $update;
      }
    }

    if (!empty($missing_updates)) {
      $path = sprintf('%s:%d', $scope->getFeature()->getFile(), $scope->getScenario()->getLine());
      $missing_updates = implode(', ', $missing_updates);
      throw new \Exception("$path\n\tThe following update hooks could not be found: $missing_updates. Fix or remove the related Behat scenario.");
    }
  }

  /**
   * Load update/post-update/deploy file for the given update hook.
   *
   * @param string $update
   *   The update hook.
   *
   * @return bool
   *   TRUE if the file could be located and loaded, FALSE otherwise.
   */
  private function loadUpdateFile(string $update): bool {
    $regex = '/^(?<module>.+?)_(?<type>post_update|update|deploy)_(?<name>.+)$/';
    if (!preg_match($regex, $update, $matches)) {
      return FALSE;
    }

    $module = $matches['module'];
    [$filename, $extension] = match ($matches['type']) {
      'post_update' => ["$module.post_update", 'php'],
      'deploy' => ["$module.deploy", 'php'],
      'update' => [$module, 'install'],
    };
    return (bool) \Drupal::moduleHandler()->loadInclude($module, $extension, $filename);
  }

}
