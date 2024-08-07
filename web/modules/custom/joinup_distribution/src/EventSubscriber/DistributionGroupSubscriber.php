<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Url;
use Drupal\joinup_group\Event\AddGroupContentEvent;
use Drupal\joinup_group\EventSubscriber\AddGroupContentEventSubscriberBase;
use Drupal\joinup_solution\Entity\SolutionInterface;

/**
 * Subscribes to Joinup Group events.
 */
class DistributionGroupSubscriber extends AddGroupContentEventSubscriberBase {

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Plugin\Context\ContextProviderInterface $releaseContext
   *   The asset release route context service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    protected ContextProviderInterface $releaseContext,
  ) {
    parent::__construct($entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public function addLinks(AddGroupContentEvent $event): void {
    // Add distribution under a release.
    $releaseContexts = $this->releaseContext->getRuntimeContexts(['release']);
    if ($releaseContexts && $releaseContexts['release']->hasContextValue()) {
      $routeParameters = [
        'node' => $releaseContexts['release']->getContextValue()->id(),
      ];
      /** @var \Drupal\Core\Config\Entity\EntityBundleWithPluralLabelsInterface $distributionBundle */
      $distributionBundle = $this->entityTypeManager->getStorage('node_type')->load('distribution');
      $this->addOneLink($distributionBundle->getSingularLabel(), $routeParameters, $event);
      // Exit here. We are also in the solution context and we don't want to
      // show this link twice.
      return;
    }

    // Add distribution under a solution.
    if ($event->getGroup() instanceof SolutionInterface) {
      parent::addLinks($event);
    }
  }

  /**
   * Add a single link.
   *
   * @todo We are overriding the route by providing an alternative route name
   *   for releases that are now nodes. This should be removed in ISAICP-8572.
   *
   * @param string $label
   *   The link label.
   * @param array $route_parameters
   *   The link URL route parameters.
   * @param \Drupal\joinup_group\Event\AddGroupContentEvent $event
   *   The event.
   */
  protected function addOneLink(string $label, array $route_parameters, AddGroupContentEvent $event): void {
    $route_name = isset($route_parameters['node']) ? 'joinup_distribution.group.add' : $this->getRouteName();
    $page_url = Url::fromRoute($route_name, $route_parameters);
    if ($page_url->access()) {
      $event->addItem([
        '#type' => 'link',
        '#title' => $this->t('Add @label', ['@label' => $label]),
        '#url' => $page_url,
        '#attributes' => ['class' => ['circle-menu__link', 'nav-link']],
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getBundles(): array {
    return [
      'node' => [
        'distribution',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRouteName(): string {
    // @todo This should be changed to the generic route in ISAICP-8572.
    return 'joinup_distribution.distribution.add';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRouteParameters(AddGroupContentEvent $event, string $entityTypeId, string $bundleId): array {
    return [
      $event->getGroup()->getEntityTypeId() => $event->getGroup()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority(): int {
    return 70;
  }

}
