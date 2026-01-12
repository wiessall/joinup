<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Plugin\OgGroupResolver;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_assessment\AssessmentInterface;
use Drupal\og\Attribute\OgGroupResolver;
use Drupal\og\GroupTypeManagerInterface;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\OgRouteGroupResolverBase;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves the group for assessment routes.
 */

#[OgGroupResolver(
  id: 'joinup_assessment_routes',
  label: new TranslatableMarkup('Routes related to assessment'),
  description: new TranslatableMarkup('Routes related to assessment')
)]
class JoinupAssessmentGroupResolver extends OgRouteGroupResolverBase {

  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    RouteMatchInterface $routeMatch,
    GroupTypeManagerInterface $groupTypeManager,
    EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityRepositoryInterface $entityRepository,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $routeMatch, $groupTypeManager, $entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('og.group_type_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OgResolvedGroupCollectionInterface $collection): void {
    if (!$this->isModerationRoute() && !$this->isAssessmentRoute()) {
      return;
    }

    /** @var \Drupal\collection\Entity\CollectionInterface $assessmentGroup */
    $assessmentGroup = $this->entityRepository
      ->loadEntityByUuid('node', AssessmentInterface::GROUP_UUID);
    if ($assessmentGroup) {
      $collection->addGroup($assessmentGroup, ['route']);
      $this->stopPropagation();
    }
  }

  /**
   * Checks if it is assessment moderation route.
   *
   * @return bool
   *   True if it is moderation route.
   */
  protected function isModerationRoute(): bool {
    $moderationRoutes = [
      'joinup_assessment.moderation',
      'joinup_assessment.edit',
      'collection/assessments/assessment-reports/questionnaire/submissions',
    ];
    if (in_array($this->routeMatch->getRouteName(), $moderationRoutes, TRUE)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if it is assessment route.
   *
   * @return bool
   *   True if it is assessment route.
   */
  protected function isAssessmentRoute(): bool {
    $webformRoutes = [
      'entity.webform.canonical',
      'entity.webform.user.submissions',
    ];
    if (!in_array($this->routeMatch->getRouteName(), $webformRoutes, TRUE)) {
      return FALSE;
    }

    $webform = $this->routeMatch->getParameter('webform');
    if (!$webform instanceof WebformInterface) {
      return FALSE;
    }

    return $webform->id() === AssessmentInterface::WEBFORM_ID;
  }

}
