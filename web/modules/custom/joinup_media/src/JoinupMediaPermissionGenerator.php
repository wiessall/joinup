<?php

declare(strict_types = 1);

namespace Drupal\joinup_media;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Permission generator for 'joinup_media' module.
 */
class JoinupMediaPermissionGenerator implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new permission generator instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Generates additional permissions to be used with Media.
   *
   * @return array
   *   A list of permissions.
   */
  public function generate(): array {
    $permissions = [];
    /** @var \Drupal\media\MediaTypeInterface $mediaType */
    foreach ($this->entityTypeManager->getStorage('media_type')->loadMultiple() as $mediaTypeId => $mediaType) {
      $permissions["add media $mediaTypeId"] = [
        'title' => $this->t("Add %type media overview", [
          '%type' => $mediaType->label(),
        ]),
      ];
    }
    return $permissions;
  }

}
