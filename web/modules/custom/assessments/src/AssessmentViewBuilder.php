<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\assessments\Entity\PublicOrganisation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View builder handler for assessment entities.
 */
class AssessmentViewBuilder extends EntityViewBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type,
  ): self {
    $instance = parent::createInstance($container, $entity_type);
    $instance->setDateFormatter($container->get('date.formatter'));
    return $instance;
  }

  /**
   * Sets the date formatter.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   */
  public function setDateFormatter(DateFormatterInterface $dateFormatter): void {
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode): void {
    if (empty($entities)) {
      return;
    }

    parent::buildComponents($build, $entities, $displays, $view_mode);

    foreach ($entities as $id => $entity) {
      $display = $displays[$entity->bundle()];

      if ($display->getComponent('submitted_by') && $entity instanceof AssessmentReport) {
        $build[$id]['submitted_by'] = $this->buildSubmittedBy($entity, $view_mode);
      }
    }
  }

  /**
   * Builds the submitted-by component of an assessment report.
   *
   * @param \Drupal\assessments\Entity\AssessmentReport $assessmentReport
   *   The assessment report.
   * @param string $viewMode
   *   The actual view mode.
   *
   * @return array
   *   The renderable array of the component.
   */
  protected function buildSubmittedBy(AssessmentReport $assessmentReport, string $viewMode): array {
    $providerField = $assessmentReport->get('provider');
    assert($providerField instanceof EntityReferenceFieldItemListInterface);
    $provider = $providerField->referencedEntities()[0] ?? NULL;
    assert($provider instanceof PublicOrganisation);
    $providerOrgTypeField = $provider->get('organisation_type');
    assert($providerOrgTypeField instanceof EntityReferenceFieldItemListInterface);
    $providerOrgType = $providerOrgTypeField->referencedEntities()[0] ?? NULL;
    $providerCountryField = $provider->get('country');
    assert($providerCountryField instanceof EntityReferenceFieldItemListInterface);
    $providerCountry = $providerCountryField->referencedEntities()[0] ?? NULL;
    $created = $assessmentReport->getCreatedTime();
    $arguments = [
      '@date' => $created ? $this->dateFormatter->format($created, 'fallback') : NULL,
      '%provider-name' => $provider->label(),
      '@provider-type' => $providerOrgType?->label(),
      '@provider-country' => $providerCountry?->label(),
    ];

    $submittedBy = $providerCountry
      ? $this->t('Submitted on @date by %provider-name, a @provider-type from @provider-country', $arguments)
      : $this->t('Submitted on @date by %provider-name, a @provider-type', $arguments);

    return [
      '#theme' => 'field',
      '#title' => $this->t('Submitted by'),
      '#label_display' => 'visually_hidden',
      '#view_mode' => $viewMode,
      '#language' => $assessmentReport->language()->getId(),
      '#field_name' => 'submitted_by',
      '#field_type' => 'extra',
      '#field_translatable' => FALSE,
      '#entity_type' => $assessmentReport->getEntityTypeId(),
      '#bundle' => $assessmentReport->bundle(),
      '#object' => $assessmentReport,
      '#items' => NULL,
      '#formatter' => NULL,
      '#is_multiple' => FALSE,
      '#third_party_settings' => [],
      [
        '#markup' => $submittedBy,
      ],
    ];
  }

}
