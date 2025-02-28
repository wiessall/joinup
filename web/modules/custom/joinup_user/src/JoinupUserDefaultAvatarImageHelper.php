<?php

declare(strict_types=1);

namespace Drupal\joinup_user;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;

/**
 * Helper class to create or update the user's avatar default image file.
 */
readonly class JoinupUserDefaultAvatarImageHelper implements ContainerInjectionInterface {

  /**
   * The user default avatar image file entity UUID.
   *
   * The UUID is the one assigned to the user photo field so there is no need
   * to manually edit and assign the file.
   *
   * @var string
   */
  private const UUID = '2e24bbc5-dedf-42b0-85cf-35940274c2f8';

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected FileSystemInterface $fileSystem,
    protected ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * Creates or updates the default user avatar image file.
   */
  public function createOrUpdate(): void {
    $source = $this->fileSystem->realpath($this->moduleExtensionList->getPath('joinup_user') . '/includes/user_icon.png');
    $uri = $this->fileSystem->copy($source, 'public://', FileExists::Replace);

    /** @var \Drupal\file\FileInterface $file */
    if (!$file = $this->entityRepository->loadEntityByUuid('file', self::UUID)) {
      $file = $this->entityTypeManager->getStorage('file')->create([
        'uri' => $uri,
      ])->set('uuid', self::UUID);
      $file->setPermanent();
    }
    $file->save();

    /** @var \Drupal\image\ImageStyleInterface $style */
    if ($style = $this->entityTypeManager->getStorage('image_style')
      ->load('profile_icon_big')) {
      // Flush the image derivative, if exists.
      $style->flush($uri);
    }
  }

}
