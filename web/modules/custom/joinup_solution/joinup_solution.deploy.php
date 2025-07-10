<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality required for configuration to be successfully imported, it
 * should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types=1);

/**
 * Add solution classification terms.
 */
function joinup_solution_deploy_200500(): string {
  $data = [
    [
      'name' => 'Complete solution',
      'description' => 'Artifact (product, tool, or digital service) that refers to the software and/or hardware system that is designed to work seamlessly with other systems or technologies realizing a business capacity. In addition to digital aspects, according to EIF the complete solution encompasses legal, governance and semantic aspects of (EIRA 6.1)',
    ],
    [
      'name' => 'Solution Component',
      'description' => 'Self-contained reusable artifact (component or service) that refers to the software and/or hardware that fulfils a specific purpose within a Complete Solution.  It should be designed considering loosely coupled, modularity, scalability and interoperability.',
    ],
    [
      'name' => 'Specification',
      'description' => 'Document with normative guidelines or characteristics that can be used consistently to ensure that materials, products, processes and services conform to one or a set of requirements (ISO). Specifications are the cornerstone of interoperability, as they set the grounds for the design and development of interoperable and reusable solutions based in common services at the lowest level (CSSV)',
    ],
    [
      'name' => 'Standard',
      'description' => 'Specification that has reached a certain maturity and widespread adoption and potentially endorsed.',
    ],
    [
      'name' => 'Framework',
      'description' => 'Specification with a structured set of guidelines, principles, standards, and tools that provides a foundation for designing, developing, managing, and governing solutions in a consistent, scalable, and interoperable way.',
    ],
    [
      'name' => 'Learning material',
      'description' => 'Solution component designed to support knowledge transfer.',
    ],
  ];

  $entityTypeManager = \Drupal::entityTypeManager();
  $termStorage = $entityTypeManager->getStorage('taxonomy_term');
  $weight = 1;
  foreach ($data as $values) {
    $term = $termStorage->create(
      [
        'vid' => 'solution_classification',
        'name' => $values['name'],
        'weight' => $weight++,
        'description' => [
          'value' => $values['description'],
          'format' => 'content_editor',
        ],
      ],
    );
    $term->save();
  }

  return 'Created solution classification terms';
}

/**
 * Add domain terms.
 */
function joinup_solution_deploy_200501(): string {
  $data = [
    'Defense',
    'Economic affairs',
    'Education',
    'Environmental Protection',
    'General public services',
    'Health',
    'Housing and Community Amenities',
    'Public Order and Safety',
    'Recreation, Culture, and Religion',
    'Social Protection',
  ];

  $entityTypeManager = \Drupal::entityTypeManager();
  $termStorage = $entityTypeManager->getStorage('taxonomy_term');
  $weight = 1;
  foreach ($data as $name) {
    $term = $termStorage->create(
      [
        'vid' => 'domain',
        'name' => $name,
        'weight' => $weight++,
      ],
    );
    $term->save();
  }

  return 'Created domain terms';
}
