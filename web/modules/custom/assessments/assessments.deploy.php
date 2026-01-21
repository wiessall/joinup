<?php

/**
 * @file
 * Deploy functions for the assessments module.
 */

declare(strict_types=1);

use Drupal\Core\Utility\UpdateException;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_assessment\AssessmentInterface as LegacyAssessmentInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;

/**
 * Create 'Code'-type vocabularies terms.
 */
function assessments_deploy_200800(): void {
  $all = [
    'assess_statement_interpretation' => [
      'positive' => 'Beneficial',
      'negative' => 'Risky',
      'neutral' => 'Negligible',
    ],
    'assess_statement_type' => [
      'legal' => 'Legal',
      'organisational' => 'Organisational',
      'semantic' => 'Semantic',
      'technical' => 'Technical',
    ],
    'assess_participant_type' => [
      'local_public_sector_body' => 'Local public sector body',
      'regional_public_sector_body' => 'Regional public sector body',
      'national_public_sector_body' => 'National public sector body',
      'european_union_institution' => 'European Union institution',
      'european_union_agency' => 'European Union agency',
      'european_union_body' => 'European Union body',
      'private_businesses' => 'Private Businesses',
    ],
    'assess_public_service_area' => [
      'core_governance_and_administration' => 'Core Governance and Administration',
      'health_and_wellbeing' => 'Health and Wellbeing',
      'education_and_research' => 'Education and Research',
      'environment_and_energy' => 'Environment and Energy',
      'economy_and_employment' => 'Economy and Employment',
      'social_and_community_services' => 'Social and Community Services',
      'security_justice_and_emergency_services' => 'Security, Justice, and Emergency Services',
      'transport_and_infrastructure' => 'Transport and Infrastructure',
      'digital_and_innovation_services' => 'Digital and Innovation Services',
      'international_and_regional_cooperation' => 'International and Regional Cooperation',
    ],
  ];

  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  foreach ($all as $vid => $terms) {
    foreach ($terms as $id => $label) {
      $storage->create(['vid' => $vid, 'name' => $label, 'id' => $id])->save();
    }
  }
}

/**
 * Unpublish assessments repository page, menu links, close webform.
 */
function assessments_deploy_200801(): void {
  /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
  $entity_repository = \Drupal::service('entity.repository');

  // Load the node at /collection/assessments/assessments-repository.
  $node = $entity_repository->loadEntityByUuid('node', LegacyAssessmentInterface::ASSESSMENT_REPORTS_UUID);
  if (!$node instanceof CustomPageInterface) {
    throw new UpdateException("Deploy hook failed: could not load custom page node by UUID, see " . __FUNCTION__);
  }

  // Unpublish it and update links pointing to it.
  $node->setNewRevision();
  $node->setRevisionLogMessage('Unpublished programmatically via update hook.');
  $node->setWorkflowState('unpublished');
  $node->skip_notification = TRUE;
  $node->save();

  /** @var \Drupal\menu_link_content\Entity\MenuLinkContent[] $links */
  $links = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['link.uri' => "entity:node/{$node->id()}"]);
  foreach ($links as $link) {
    $link->set('link', [['uri' => 'internal:/collection/assessments/report/repository']]);
    $link->save();
  }

  // Close the assessment webform.
  $webform = Webform::load(LegacyAssessmentInterface::WEBFORM_ID);
  \assert($webform instanceof WebformInterface);
  $webform->setStatus(WebformInterface::STATUS_CLOSED);
  $webform->save();
}

/**
 * Implements hook_deploy_name().
 */
function assessments_deploy_200802(): void {
  // Install the public organisation vocabulary and entities.
  \Drupal::moduleHandler()->loadInclude('assessments', 'install', 'assessments');
  assessments_install();
}
