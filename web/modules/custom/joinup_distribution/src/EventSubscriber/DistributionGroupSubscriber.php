<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\joinup_group\Event\AddGroupContentEvent;
use Drupal\joinup_group\EventSubscriber\AddGroupContentEventSubscriberBase;
use Drupal\solution\Entity\SolutionInterface;

/**
 * Subscribes to Joinup Group events.
 */
class DistributionGroupSubscriber extends AddGroupContentEventSubscriberBase {

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Plugin\Context\ContextProviderInterface $assetReleaseContext
   *   The asset release route context service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    protected ContextProviderInterface $assetReleaseContext,
  ) {
    parent::__construct($entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public function addLinks(AddGroupContentEvent $event): void {
    // Add distribution under a release.
    $releaseContexts = $this->assetReleaseContext->getRuntimeContexts(['release']);
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
    return 'joinup_distribution.distribution.add';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRouteParameters(AddGroupContentEvent $event, string $entityTypeId, string $bundleId): array {
    return [
      'rdf_entity' => $event->getGroup()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority(): int {
    return 70;
  }

}
