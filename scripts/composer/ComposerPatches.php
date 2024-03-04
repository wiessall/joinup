<?php

declare(strict_types=1);

namespace Joinup\composer;

use Composer\Composer;
use Composer\Script\Event;

/**
 * Script handler class to validate composer patches.
 */
final class ComposerPatches {

  const PATCH_DIR = './resources/patch/php';

  /**
   * Validates patches.
   */
  public static function validate(Event $event) {
    $composer = $event->getComposer();
    $patches = self::getComposerPatches($composer);
    if (!$patches) {
      return;
    }

    $patches_paths = [];
    foreach ($patches as $patch) {
      if (!file_exists($patch)) {
        throw new \Exception("Patch $patch could not be located or is not local.");
      }

      $patches_paths[] = realpath($patch);
    }

    // Ensures PATCH_DIR doesn't contain any stale/unused patch.
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::PATCH_DIR));
    foreach ($iterator as $file) {
      if ($file->isFile()) {
        if (!in_array($file->getRealPath(), $patches_paths, TRUE)) {
          throw new \Exception("Patch {$file->getPathname()} is not referenced in composer.json. Ensure it is being used, or consider removing it if unnecessary.");
        }
      }
    }
  }

  /**
   * Get all composer.json patches as a flat array.
   */
  private static function getComposerPatches(Composer $composer): ?array {
    $patches = [];
    $extra = $composer->getPackage()->getExtra();
    foreach ($extra['patches'] ?? [] as $package_patches) {
      $patches = array_merge($patches, array_values($package_patches));
    }
    return $patches;
  }

}
