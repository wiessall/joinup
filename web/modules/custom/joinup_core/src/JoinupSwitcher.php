<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;

/**
 * Represents a file-based implementation of the ConfigReadOnlyInterface.
 */
class JoinupSwitcher implements JoinupSwitcherInterface {

  protected const string DIR = 'private://killswitch';

  protected const array FEATURES = ['config-readonly', 'eu-oss-catalogue'];

  public function __construct(protected FileSystemInterface $fileSystem) {}

  /**
   * {@inheritdoc}
   */
  public function isEnabled(string $feature): bool {
    $file = $this->fileSystem->realpath($this->pathToFile($feature));
    if ($file && file_exists($file)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function enable(string $feature): void {
    if (!$this->isEnabled('config-readonly')) {
      $file = $this->fileSystem->realpath($this->pathToFile($feature));
      $this->fileSystem->unlink($file);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function disable(string $feature): void {
    $file = $this->fileSystem->realpath($this->pathToFile($feature));
    $this->fileSystem->saveData('', $file, FileExists::Replace);
  }

  /**
   * Returns the file path to the kill-switch file.
   *
   * @param string $feature
   *   The feature. E.g. 'config-readonly'.
   *
   * @return string
   *   The file path
   */
  protected function pathToFile(string $feature): string {
    assert(in_array($feature, static::FEATURES, TRUE));
    return static::DIR . "/disable-$feature";
  }

}
