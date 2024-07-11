<?php

declare(strict_types=1);

namespace Drupal\joinup_user;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper class to create or update the user's avatar default image file.
 */
class JoinupUserDefaultAvatarImageHelper implements ContainerInjectionInterface {

  /**
   * The user default avatar image file entity UUID.
   *
   * The UUID is the one assigned to the user photo field so there is no need
   * to manually edit and assign the file.
   *
   * @var string
   */
  private const UUID = '2e24bbc5-dedf-42b0-85cf-35940274c2f8';

  /**
   * Constructs a new helper class instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityRepositoryInterface $entityRepository,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly ModuleExtensionList $moduleExtensionList,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('file_system'),
      $container->get('extension.list.module'),
    );
  }

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
    if ($style = $this->entityTypeManager->getStorage('image_style')->load('profile_icon_big')) {
      // Flush the image derivative, if exists.
      $style->flush($uri);
    }
  }

}
