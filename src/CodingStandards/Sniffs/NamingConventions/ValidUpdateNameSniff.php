<?php

declare(strict_types=1);

namespace Joinup\CodingStandards\Sniffs\NamingConventions;

use Composer\Semver\Comparator;
use Drupal\Component\Serialization\Yaml;
use Drupal\Sniffs\NamingConventions\ValidFunctionNameSniff;
use Gitonomy\Git\Exception\ProcessException;
use Gitonomy\Git\Repository;
use PHP_CodeSniffer\Files\File;

/**
 * Checks the naming of (post)update/deploy functions.
 *
 * Interoperable Europe Portal offers only _contiguous upgrades_. For instance,
 * if your project is currently on Interoperable Europe Portal `v1.39.2`,
 * and the latest stable version is `v1.42.0`, then you cannot upgrade directly
 * to the latest version. Instead, you should upgrade first to `v1.40.0`,
 * second to `v1.40.1` (if exists) and, finally, to `v1.42.0`.
 *
 * The Interoperable Europe Portal update, post-update and deploy scripts
 * namings are following this pattern:
 * @code
 * function mymodule_update_106100() {...}
 * @endcode
 * or
 * @code
 * function mymodule_post_update_207503() {...}
 * @endcode
 * or
 * @code
 * function mymodule_deploy_1008101() {...}
 * @endcode
 * The (post)update/deploy identifier (the numeric part consists in six or seven
 * digits with the following meaning:
 * - The last two digits are an integer that sets the weight within updates or
 *   post updates from the same extension (module or profile). `00` is the first
 *   (post)update/deploy that applies.
 * - The middle three digits are the Interoperable Europe Portal minor version.
 * - The first one or two digits are the Interoperable Europe Portal major
 * version. It cannot start with '0'.
 *
 * Given the above example:
 *
 * - `function mymodule_update_106100() {...}`: Was applied in Interoperable
 *   Europe Portal `v1.61.x` as the first update of the `mymodule` module
 *   (`1` major version, `061` minor version, `00` update weight
 *   within the module).
 * - `function mymodule_post_update_207503() {...}`: Was applied in
 *   Interoperable Europe Portal `v2.75.x` as the fourth post update of the
 *   `mymodule` module (`2` major version, `075` minor version, `03`
 *   post-update weight within the module).
 * - `function mymodule_deploy_1008101() {...}`: Was applied in Interoperable
 *   Europe Portal `v10.81.x` as the second deploy of the `mymodule` module
 *   (`10` major version, `081` minor version, `01` deploy weight within
 *   the module).
 *
 * This sniff checks if the (post)update or deploy is correctly formed and if
 * the version represented by the (post)update/deploy identifier was not already
 * released.
 */
class ValidUpdateNameSniff extends ValidFunctionNameSniff {

  /**
   * Static cache for the list of enabled extensions.
   *
   * @var string[]
   */
  protected static array $enabledExtensions;

  /**
   * Static cache for latest stable Git tag.
   */
  protected static string $latestStableTag;

  /**
   * Whether the current commit and the latest stable tag commit are the same.
   */
  protected static bool $exactMatch = FALSE;

  /**
   * Static cache for next version candidates.
   *
   * @var string[]
   */
  protected static array $nextVersionCandidates;

  /**
   * Static cache of the Git repository instance.
   */
  protected static Repository $git;

  /**
   * {@inheritdoc}
   */
  protected function processTokenOutsideScope(File $phpcsFile, $stackPtr): void {
    $functionName = $phpcsFile->getDeclarationName($stackPtr);

    if ($functionName === NULL || !preg_match('#_((post_)?update|deploy)_#', $functionName)) {
      // Ignore closures and functions that cannot be (post)updates.
      return;
    }

    $match = FALSE;
    foreach (static::getEnabledExtensions() as $extension) {
      if (preg_match("#^{$extension}_((post_)?update|deploy)_(.*)$#", $functionName, $found)) {
        $match = TRUE;
        $name = $found[3];
        $updateType = !empty($found[2]) ? 'post update' : $found[1];
        break;
      }
    }

    // Not a (post)update or deploy.
    if (!$match) {
      return;
    }

    if ($name === 'dependencies') {
      // Exception for hook_update_dependencies() case.
      return;
    }

    $error = NULL;
    if (!ctype_digit($name) || !in_array(strlen($name), [6, 7])) {
      $error = "Invalid '%s' identifier. Expected 6 or 7 digits:\n- 1-2 digits: Interoperable Europe Portal major version\n- 3 digits: Interoperable Europe Portal minor version\n- 2 digits: The %s weight";
      $data = [$name, $updateType];
    }
    elseif ($name[0] === '0') {
      $error = "The %s identifier cannot start with '0' ('%s' given)";
      $data = [$updateType, $name];
    }
    else {
      $zero_padded_name = sprintf('%07d', (int) $name);
      $majorVersion = (int) substr($zero_padded_name, 0, 2);
      $minorVersion = (int) substr($zero_padded_name, 2, 3);
      $minorVersionTag = "{$majorVersion}.{$minorVersion}";

      if (static::isAlreadyApplied($minorVersionTag)) {
        $error = "Remove %s(). Already applied in %s";
        $data = [$functionName, $minorVersionTag];
      }

      // This update hasn't been yet applied. Check its identifier.
      if (!$error && static::$exactMatch === FALSE) {
        $nextVersionCandidates = static::getNextVersionCandidates();
        if (!in_array(substr($zero_padded_name, 0, 5), $nextVersionCandidates)) {
          $digits_count = strlen($name) === 7 ? 5 : 4;
          $error = "Invalid '%s' identifier. The first %s digits should be '%s' or '%s'";
          $data = [
            $name,
            $digits_count,
            $nextVersionCandidates[0],
            $nextVersionCandidates[1],
          ];
        }
      }
    }

    if ($error) {
      $phpcsFile->addError($error, $stackPtr, 'InvalidUpdateId', $data);
    }
  }

  /**
   * Checks whether the (post)update/deploy has been already applied.
   *
   * @param string $minorVersionTag
   *   The update identifier as a tag.
   *
   * @return bool
   *   Whether the (post)update/deploy has been already applied.
   */
  protected static function isAlreadyApplied(string $minorVersionTag): bool {
    $tag = "$minorVersionTag.0";

    if (Comparator::lessThan($tag, static::getLatestStableTag())) {
      return TRUE;
    }
    if (Comparator::greaterThan($tag, static::getLatestStableTag())) {
      return FALSE;
    }

    if (!isset(static::$exactMatch)) {
      try {
        static::git()->run('describe', ['--tags', '--exact-match']);
        // We're on the latest stable tag commit.
        static::$exactMatch = TRUE;
      }
      catch (ProcessException $exception) {
        // We're one or many commits away from the latest stable tag.
      }
    }

    return !static::$exactMatch;
  }

  /**
   * Returns the latest semver valid and stable Git tag.
   *
   * @return string
   *   The latest semver valid and stable Git tag with the potential leading 'v'
   *   stripped out.
   */
  protected static function getLatestStableTag(): string {
    if (!isset(static::$latestStableTag)) {
      $tagsString = trim(static::git()->run('tag', ['--sort=creatordate']));
      $tags = array_reverse(preg_split('/\s/', $tagsString));
      foreach ($tags as $tag) {
        // Only support stable release tags. The regexp is borrowed and adapted
        // from \Composer\Semver\VersionParser::normalize().
        if (preg_match('{^v?(\d{1,5})(\.\d++)?(\.\d++)?(\.\d++)?$}i', $tag)) {
          // Remove a potential leading 'v'.
          static::$latestStableTag = ltrim($tag, 'v');
          break;
        }
      }
    }

    return static::$latestStableTag;
  }

  /**
   * Returns a list of possible next versions.
   *
   * @return array
   *   A two elements array.
   */
  protected static function getNextVersionCandidates(): array {
    if (!isset(static::$nextVersionCandidates)) {
      [$majorVersion, $minorVersion] = explode('.', static::getLatestStableTag());
      static::$nextVersionCandidates = [
        // Next minor version.
        sprintf('%d%03d', $majorVersion, ++$minorVersion),
        // Next major version.
        sprintf('%d%03d', ++$majorVersion, 0),
      ];
    }
    return self::$nextVersionCandidates;
  }

  /**
   * Returns all enabled extensions.
   *
   * @return string[]
   *   Enabled extensions.
   */
  protected static function getEnabledExtensions(): array {
    if (!isset(static::$enabledExtensions)) {
      static::$enabledExtensions = array_keys(Yaml::decode(file_get_contents(getcwd() . '/config/sync/core.extension.yml'))['module']);
    }
    return static::$enabledExtensions;
  }

  /**
   * Returns the Git repository instance.
   *
   * @return \Gitonomy\Git\Repository
   *   The Git repository instance.
   */
  protected static function git(): Repository {
    if (!isset(static::$git)) {
      static::$git = new Repository(getcwd());
    }
    return static::$git;
  }

}
