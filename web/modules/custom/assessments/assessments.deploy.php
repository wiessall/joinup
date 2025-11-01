<?php

/**
 * @file
 * Deploy functions for the assessments module.
 */

declare(strict_types=1);

/**
 * Create EU institutions as public_organisation assessment entities.
 */
function assessments_deploy_200700(): void {
  $entity_type_manager = \Drupal::entityTypeManager();
  $storage = $entity_type_manager->getStorage('assessment');

  $institutions = [
    'european_union_institution' => [
      'European Parliament',
      'European Council',
      'Council of the European Union',
      'European Commission',
      'Court of Justice of the European Union',
      'European Central Bank',
      'European Court of Auditors',
    ],
    'european_union_body' => [
      'European External Action Service',
      'European Economic and Social Committee',
      'European Committee of the Regions',
      'European Investment Bank',
      'European Ombudsman',
      'European Data Protection Supervisor',
      'European Data Protection Board',
      'European Public Prosecutor\'s Office',
    ],
    'european_union_agency' => [
      'Agency for Support for BEREC',
      'Agency for the Cooperation of Energy Regulators',
      'Authority for European Political Parties and European Political Foundations',
      'Community Plant Variety Office',
      'Eurojust',
      'European Agency for Safety and Health at Work',
      'European Asylum Support Office',
      'European Banking Authority',
      'European Border and Coast Guard Agency',
      'European Centre for Disease Prevention and Control',
      'European Centre for the Development of Vocational Training',
      'European Chemicals Agency',
      'European Environment Agency',
      'European Fisheries Control Agency',
      'European Food Safety Authority',
      'European Foundation for the Improvement of Living and Working Conditions',
      'European GNSS Agency',
      'European Institute for Gender Equality',
      'European Insurance and Occupational Pensions Authority',
      'European Maritime Safety Agency',
      'European Medicines Agency',
      'European Monitoring Centre for Drugs and Drug Addiction',
      'European Securities and Markets Authority',
      'European Training Foundation',
      'European Union Agency for Cybersecurity',
      'European Union Agency for Fundamental Rights',
      'European Union Agency for Law Enforcement Cooperation',
      'European Union Agency for Law Enforcement Training',
      'European Union Agency for Railways',
      'European Union Agency for the Operational Management of Large-Scale IT Systems in the Area of Freedom, Security and Justice',
      'European Union Aviation Safety Agency',
      'European Union Intellectual Property Office',
      'Single Resolution Board',
      'Translation Centre for the Bodies of the European Union',
    ],
  ];

  foreach ($institutions as $organisation_type => $institution_names) {
    foreach ($institution_names as $institution_name) {
      $entity = $storage->create([
        'type' => 'public_organisation',
        'name' => $institution_name,
        'organisation_type' => $organisation_type,
      ]);

      try {
        $entity->save();
      }
      catch (\Exception $e) {
        \Drupal::logger('assessments')->error('Failed to create EU institution @name: @error', [
          '@name' => $institution_name,
          '@error' => $e->getMessage(),
        ]);
      }
    }
  }
}
