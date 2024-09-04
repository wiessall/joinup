<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;

/**
 * Represents a file-based implementation of the ConfigReadOnlyInterface.
 */
class FileConfigReadOnly implements ConfigReadOnlyInterface {

  protected const string FILE = 'private://killswitch/disable-config-readonly';

  public function __construct(protected FileSystemInterface $fileSystem) {}

  /**
   * {@inheritdoc}
   */
  public function isReadOnlyEnabled(): bool {
    $file = $this->fileSystem->realpath(static::FILE);
    if ($file && file_exists($file)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function enableReadOnly(): void {
    if (!$this->isReadOnlyEnabled()) {
      $file = $this->fileSystem->realpath(static::FILE);
      $this->fileSystem->unlink($file);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function disableReadOnly(): void {
    $file = $this->fileSystem->realpath(static::FILE);
    $this->fileSystem->saveData('', $file, FileExists::Replace);
  }

}
