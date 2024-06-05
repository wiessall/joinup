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
   *
   * @param \Composer\Script\Event $event
   *   The composer event.
   */
  public static function validate(Event $event): void {
    $composer = $event->getComposer();
    $patches = self::getComposerPatches($composer);
    if (!$patches) {
      return;
    }

    $patchesPaths = [];
    foreach ($patches as $patch) {
      if (!file_exists($patch)) {
        throw new \Exception("Patch $patch could not be located or is not local.");
      }

      $patchesPaths[] = realpath($patch);
    }

    // Ensures PATCH_DIR doesn't contain any stale/unused patch.
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::PATCH_DIR));
    foreach ($iterator as $file) {
      if ($file->isFile() && !in_array($file->getRealPath(), $patchesPaths, TRUE)) {
        throw new \Exception("Patch {$file->getPathname()} is not referenced in composer.json. Ensure it is being used, or consider removing it if unnecessary.");
      }
    }
  }

  /**
   * Get all composer.json patches as a flat array.
   *
   * @param \Composer\Composer $composer
   *   Composer.
   *
   * @return array
   *   List of patches.
   */
  private static function getComposerPatches(Composer $composer): array {
    $patches = [];
    $extra = $composer->getPackage()->getExtra();
    foreach ($extra['patches'] ?? [] as $packagePatches) {
      $patches = array_merge($patches, array_values($packagePatches));
    }
    return $patches;
  }

}
