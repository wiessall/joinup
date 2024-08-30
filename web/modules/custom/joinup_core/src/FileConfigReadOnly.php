<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

/**
 * Represents a file-based implementation of the ConfigReadOnlyInterface.
 */
class FileConfigReadOnly implements ConfigReadOnlyInterface {

  /**
   * File name of the disabled config readonly.
   *
   * @var string
   */
  protected const string FILE_NAME = 'disable-config-readonly';

  public function __construct(protected KillSwitchInterface $killSwitch) {}

  /**
   * {@inheritdoc}
   */
  public function isReadOnlyEnabled(): bool {
    return !$this->killSwitch->exists(self::FILE_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function enableReadOnly(): void {
    $this->killSwitch->remove(self::FILE_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function disableReadOnly(): void {
    $this->killSwitch->create(self::FILE_NAME);
  }

}
