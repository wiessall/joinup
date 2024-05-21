<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller of the route used by "joinup_core_more_in_modal" formatter.
 *
 * @see \Drupal\joinup_core\Plugin\Field\FieldFormatter\MoreInModalFormatter
 */
class ModalFormatterEntityViewController implements ContainerInjectionInterface {

  /**
   * Constructs a new ModalFormatterEntityViewController instance.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displayRepository
   *   The entity display repository.
   */
  public function __construct(
    protected EntityDisplayRepositoryInterface $displayRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_display.repository')
    );
  }

  /**
   * Returns the title of the modal page.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host entity of the field.
   * @param string $field
   *   The name of the field to render in modal.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label of the field, or the entity if the field label is empty.
   */
  public function title(ContentEntityInterface $entity, string $field): string|TranslatableMarkup {
    $title = $field && !empty($entity->getFieldDefinitions()[$field])
      ? $entity->getFieldDefinitions()[$field]->getLabel()
      : $entity->label();

    // FieldDefinitionInterface::getLabel() might return NULL.
    return $title ?? $entity->label();
  }

  /**
   * Provides the route callback for the modal route.
   *
   * This controller assumes that it is only invoked for users with the needed
   * permission.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity object.
   * @param string $viewMode
   *   The name of the view mode.
   * @param string $field
   *   The name of the field.
   *
   * @return array
   *   Renderable array of the entity field.
   */
  public function buildViewModeResponse(
    ContentEntityInterface $entity,
    string $viewMode,
    string $field,
  ): array {
    $build = $this->buildEntity($entity, $viewMode, $field);

    // Source entity view display entity must also be cached.
    $sourceViewDisplay = $this->displayRepository->getViewDisplay($entity->getEntityTypeId(), $entity->bundle(), $viewMode);
    CacheableMetadata::createFromObject($sourceViewDisplay)
      ->applyTo($build);
    if ($sourceViewDisplay->isNew() || !$sourceViewDisplay->status()) {
      CacheableMetadata::createFromObject($this->displayRepository->getViewDisplay($entity->getEntityTypeId(), $entity->bundle())
      )->applyTo($build);
    }

    return $build;
  }

  /**
   * Determines whether the "modal" page can be accessed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity from the route slug.
   * @param string $field
   *   The field to render.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(EntityInterface $entity, string $field): AccessResultInterface {
    $accessResult = AccessResult::forbidden();

    if (
      $entity instanceof ContentEntityInterface
      && $entity->hasField($field)
      && !$entity->get($field)->isEmpty()
    ) {
      $accessResult = $entity->get($field)->access('view', NULL, TRUE);
    }

    return $accessResult->addCacheableDependency($entity)
      ->cachePerUser()
      ->cachePerPermissions();
  }

  /**
   * Builds the renderable array of the field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object.
   * @param string $viewMode
   *   The name of the view mode.
   * @param string $fieldName
   *   The name of the field to render.
   *
   * @return array[]
   *   The renderable array.
   */
  protected function buildEntity(ContentEntityInterface $entity, string $viewMode, string $fieldName): array {
    // Field-only renders do not get cache tag by default. We add the entire
    // object in ::buildAjaxResponse.
    $build = $entity->get($fieldName)->view($viewMode);
    // Field title might be set to be displayed. Since it is the label of the
    // modal dialog, we set it to be hidden.
    $build['#label_display'] = 'hidden';
    // Change theme function.
    $build['#theme'] = 'field__in_modal';

    return $build;
  }

}
