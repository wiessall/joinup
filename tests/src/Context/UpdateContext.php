<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Context for update tests.
 */
class UpdateContext extends RawDrupalContext {

  private const string UPDATE_DIR = 'tests/features/update';

  /**
   * Ensures that update-related features use proper tags.
   */
  #[BeforeScenario]
  public function checkUpdateExists(BeforeScenarioScope $scope): void {
    $feature_path = realpath(DRUPAL_ROOT . '/../' . self::UPDATE_DIR);
    if (!str_starts_with($scope->getFeature()->getFile(), $feature_path)) {
      // Only handle features in the update dir.
      return;
    }

    $violations = $this->getViolations($scope);
    if ($violations) {
      $path = sprintf('%s:%d', $scope->getFeature()->getFile(), $scope->getScenario()->getLine());
      throw new \Exception("$path\n\t" . implode("\n\t", $violations));
    }
  }

  /**
   * Returns violations for the current scope.
   */
  private function getViolations(BeforeScenarioScope $scope): array {
    $violations = [];

    $tags = array_unique(array_merge($scope->getFeature()->getTags(), $scope->getScenario()->getTags()));
    if (!in_array('group-clone', $tags)) {
      $violations[] = sprintf('Scenarios inside %s should always be tagged with @group-clone', self::UPDATE_DIR);
    }

    $updates = array_filter($tags, function (string $tag): bool {
      return str_starts_with($tag, 'update:');
    });

    if (!$updates) {
      $violations[] = sprintf('Scenarios inside %s should always be tagged with @update:[function], where [function] is the update/post-update/deploy function.', self::UPDATE_DIR);
      return $violations;
    }

    $missing_updates = array_filter($updates, function ($tag) {
      [, $update] = explode(':', $tag, 2);
      return !$this->loadUpdateFile($update) || !function_exists($update);
    });

    if (!empty($missing_updates)) {
      $missing_updates = implode(', ', $missing_updates);
      $violations[] = "The following update hooks could not be found: $missing_updates. Fix or remove the related Behat scenario.";
    }

    return $violations;
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
