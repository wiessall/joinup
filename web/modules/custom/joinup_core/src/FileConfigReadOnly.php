<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\File\FileSystemInterface;

/**
 * Represents a file-based implementation of the ConfigReadOnlyInterface.
 */
class FileConfigReadOnly implements ConfigReadOnlyInterface {

  /**
   * File name of the disabled config readonly.
   *
   * @var string
   */
  const string FILE_NAME = 'disable-config-readonly';

  public function __construct(protected FileSystemInterface $fileSystem) {}

  /**
   * {@inheritdoc}
   */
  public function isReadOnlyEnabled(): bool {
    return !file_exists($this->pathToReadOnlyFile()());
  }

  /**
   * {@inheritdoc }
   */
  public function enableReadOnly(): void {
    if (!$this->isReadOnlyEnabled()) {
      $this->fileSystem->unlink($this->pathToReadOnlyFile()());
    }
  }

  /**
   * {@inheritdoc }
   */
  public function disableReadOnly(): void {
    touch($this->pathToReadOnlyFile()());
  }

  /**
   * Returns the file path to disable the config readonly flag.
   *
   * @return string
   *   The file path
   */
  protected function pathToReadOnlyFile(): string {
    return DRUPAL_ROOT . '/../' . self::FILE_NAME;
  }

}
