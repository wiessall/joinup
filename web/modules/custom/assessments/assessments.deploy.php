<?php

/**
 * @file
 * Deploy functions for the assessments module.
 */

declare(strict_types=1);

use Drupal\assessments\WebformSubmissionMigrator;
use Drupal\taxonomy\Entity\Term;

/**
 * Fix/Update the public service thematic areas.
 */
function assessments_deploy_200804(): string {
  $vid = 'assess_public_service_area';

  // Clear existing terms.
  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $terms = $storage->loadTree($vid);
  foreach ($terms as $term) {
    $term_entity = $storage->load($term->tid);
    if ($term_entity) {
      $term_entity->delete();
    }
  }

  // COFOG classification of the functions of government (divisions).
  $areas = [
    'general_public_services' => 'General public services',
    'defence' => 'Defence',
    'public_order_and_safety' => 'Public order and safety',
    'economic_affairs' => 'Economic affairs',
    'environmental_protection' => 'Environmental protection',
    'housing_and_community_amenities' => 'Housing and community amenities',
    'health' => 'Health',
    'recreation_culture_and_religion' => 'Recreation, culture and religion',
    'education' => 'Education',
    'social_protection' => 'Social protection',
  ];

  foreach ($areas as $id => $name) {
    $term = Term::create([
      'name' => $name,
      'vid' => $vid,
      'id' => $id,
    ]);
    $term->save();
  }

  return (string) count($areas) . ' public service area terms created.';
}

/**
 * Migrate webform assessment submissions to assessment report entities.
 */
function assessments_deploy_200805(): void {
  $migrator = \Drupal::service(WebformSubmissionMigrator::class);
  $result = $migrator->migrateAll();
  \Drupal::logger('assessments')->info('Webform migration completed: @migrated migrated, @skipped skipped, @failed failed.', [
    '@migrated' => $result['migrated'],
    '@skipped' => $result['skipped'],
    '@failed' => $result['failed'],
  ]);
}
