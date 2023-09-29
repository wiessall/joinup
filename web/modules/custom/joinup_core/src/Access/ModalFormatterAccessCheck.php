<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Access checker of modal routes used by "joinup_core_more_in_modal" formatter.
 */
class ModalFormatterAccessCheck implements AccessInterface {

  /**
   * Constructs a ModalFormatterAccessCheck instance.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displayRepository
   *   The display repository.
   */
  public function __construct(
    protected EntityDisplayRepositoryInterface $displayRepository
  ) {}

  /**
   * Evaluates access for the modal formatter route.
   *
   * Entity access check might be declared by the original entity canonical
   * route - and modal routes are based on those routes. We copy and modify them
   * to be a modal route in ModalFormatterRouteSubscriber.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $viewMode
   *   The view mode to use for rendering.
   * @param string $field
   *   The name of the field to render.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   *
   * @see \Drupal\joinup_core\EventSubscriber\ModalFormatterRouteSubscriber
   */
  public function access(
    RouteMatchInterface $routeMatch,
    Request $request,
    ContentEntityInterface $entity,
    string $viewMode,
    string $field
  ): AccessResultInterface {
    // We are defaulting to deny access for AJAX requests.
    $accessResult = $routeMatch->getParameter('js') === 'ajax'
      ? new AccessResultForbidden()
      : new AccessResultAllowed();

    $viewDisplays = $this->getViewDisplays($entity, $viewMode);

    // Last view display in the array is the active one. If we are about to
    // display a modal (and not send a redirect) then we only allow access if
    // the field is displayed in the corresponding entity view display.
    if (
      $request->query->get('_wrapper_format') === 'drupal_ajax' &&
      $entity->hasField($field) &&
      !$entity->get($field)->isEmpty() &&
      end($viewDisplays)->getComponent($field)
    ) {
      $accessResult = new AccessResultAllowed();
    }

    $accessResult->addCacheableDependency($entity);
    reset($viewDisplays);
    foreach ($viewDisplays as $viewDisplay) {
      $accessResult->addCacheableDependency($viewDisplay);
    }

    return $accessResult;
  }

  /**
   * Returns the related entity view displays of the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $viewMode
   *   View mode of the field to render in modal.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface[]
   *   Related entity view displays of the entity. If the named display is new,
   *   or it is disabled, then the default view display is also returned.
   */
  protected function getViewDisplays(ContentEntityInterface $entity, string $viewMode): array {
    $sourceViewDisplay = $this->displayRepository
      ->getViewDisplay($entity->getEntityTypeId(), $entity->bundle(), $viewMode);
    $displaysToCacheBy[] = $sourceViewDisplay;
    if ($sourceViewDisplay->isNew() || !$sourceViewDisplay->status()) {
      $displaysToCacheBy[] = $this->displayRepository
        ->getViewDisplay($entity->getEntityTypeId(), $entity->bundle());
    }

    return $displaysToCacheBy;
  }

}
