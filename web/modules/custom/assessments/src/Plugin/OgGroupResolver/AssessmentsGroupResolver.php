<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\OgGroupResolver;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\assessments\AssessmentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\Attribute\OgGroupResolver;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\OgRouteGroupResolverBase;

/**
 * Resolves the group for assessments routes.
 */
#[OgGroupResolver(
  id: 'assessments_group',
  label: new TranslatableMarkup('Assessments group'),
  description: new TranslatableMarkup('Resolves the group for assessments routes.')
)]
class AssessmentsGroupResolver extends OgRouteGroupResolverBase {

  /**
   * {@inheritdoc}
   */
  public function resolve(OgResolvedGroupCollectionInterface $collection): void {
    match ($this->routeMatch->getRouteName()) {
      'view.assessments_reports.reports' => $this->resolveReportsRoute($collection),
      'assessments.moderate' => $this->resolveAssessmentsCollection($collection),
      default => NULL,
    };
  }

  /**
   * Resolves the assessments reports route.
   */
  private function resolveReportsRoute(OgResolvedGroupCollectionInterface $collection): void {
    $nid = $this->routeMatch->getParameter('node');
    if (!is_numeric($nid)) {
      return;
    }

    $group = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$group instanceof GroupInterface || $group->uuid() !== AssessmentInterface::GROUP_UUID) {
      return;
    }

    $collection->addGroup($group, ['route']);
    $this->stopPropagation();
  }

  /**
   * Resolves the assessments collection for moderation routes.
   */
  private function resolveAssessmentsCollection(OgResolvedGroupCollectionInterface $collection): void {
    $group = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['uuid' => AssessmentInterface::GROUP_UUID]);

    if (empty($group)) {
      return;
    }

    $group = reset($group);
    if (!$group instanceof GroupInterface) {
      return;
    }

    $collection->addGroup($group, ['route']);
    $this->stopPropagation();
  }

}
