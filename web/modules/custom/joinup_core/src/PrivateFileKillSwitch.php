<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;

/**
 * Private file kill switch.
 */
class PrivateFileKillSwitch implements KillSwitchInterface {

  const string DIRECTORY = 'private://file-switcher';

  public function __construct(protected FileSystemInterface $fileSystem) {}

  /**
   * {@inheritdoc}
   */
  public function remove(string $type): void {
    if ($this->exists($type)) {
      $realPath = $this->fileSystem->realpath($this->getFilePath($type));
      $this->fileSystem->unlink($realPath);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function create(string $type): void {
    $realPath = $this->fileSystem->realpath($this->getFilePath($type));
    $directory = $this->fileSystem->dirname($realPath);
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new FileWriteException("Could not create or write to directory '$directory'");
    }
    $this->fileSystem->saveData('', $realPath, FileExists::Replace);
  }

  /**
   * {@inheritdoc}
   */
  public function exists(string $type): bool {
    $realPath = $this->fileSystem->realpath($this->getFilePath($type));

    if ($realPath && file_exists($realPath)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the file path.
   *
   * @param string $type
   *   Type.
   *
   * @return string
   *   The file path.
   */
  protected function getFilePath(string $type): string {
    return self::DIRECTORY . DIRECTORY_SEPARATOR . basename($type);
  }

}
