<?php

declare(strict_types=1);

namespace Drupal\joinup_test_content;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\meta_entity\MetaEntityRepositoryInterface;
use Drupal\rdf_sync\RdfSyncMapperInterface;

/**
 * Sets up programmatic test content during module installation.
 */
class TestContentSetup {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly MetaEntityRepositoryInterface $metaEntityRepository,
    protected readonly RdfSyncMapperInterface $rdfSyncMapper,
  ) {
  }

  /**
   * Adds a configurable_listing paragraph to the eGov topic meta entity.
   */
  public function setupTopicPage(): void {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $term_storage->loadByProperties([
      'uuid' => 'ee3076fa-ee59-41b8-997e-b8e0fab5559f',
    ]);
    if (empty($terms)) {
      return;
    }
    $term = reset($terms);

    $meta_entity = $this->metaEntityRepository->getMetaEntityForEntity($term, 'topic');
    if (!$meta_entity) {
      return;
    }

    // Create a configurable_listing paragraph in "grid" mode.
    $listing_paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => 'configurable_listing',
      'paragraph_heading' => 'Solutions and resources',
      'list_style' => 'grid',
      'content_listing' => [
        [
          'value' => [
            'enabled' => 1,
            'query_presets' => '',
            'limit' => 10,
            'global_search' => FALSE,
            'show_shared' => FALSE,
          ],
        ],
      ],
    ]);
    $listing_paragraph->save();

    $meta_entity->set('published', TRUE);
    $meta_entity->get('topics_paragraph')->appendItem($listing_paragraph);
    $meta_entity->save();
  }

  /**
   * Creates an EIF perspective for the test solution.
   */
  public function setupEifPerspective(): void {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $solutions = $node_storage->loadByProperties([
      'uuid' => 'fc99c508-84dc-4646-a112-5b37bff6f2a7',
    ]);
    if (empty($solutions)) {
      return;
    }
    $solution = reset($solutions);

    // Load the EIF solution type term ("Common frameworks").
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $term_storage->loadByProperties([
      'uuid' => 'd131f884-9a73-4aa7-8191-d3dde2e5d3a4',
    ]);
    $solution_type = $terms ? reset($terms) : NULL;
    if (!$solution_type) {
      return;
    }

    // Load the EIF recommendation term ("Recommendation 1").
    $recommendation = NULL;
    $rec_terms = $term_storage->loadByProperties([
      'uuid' => 'f32a68f0-6e43-4bb2-bb70-96fe3fd65a0b',
    ]);
    $rec_term = $rec_terms ? reset($rec_terms) : NULL;
    if ($rec_term) {
      $recommendation = [
        'target_id' => $rec_term->id(),
        'value' => 'This solution supports subsidiarity and proportionality principles.',
        'format' => 'basic_html',
      ];
    }

    $eif_values = [
      'type' => 'eif_perspective',
      'uuid' => '501120ce-502c-4ed1-89d0-c61a6daaae78',
      'target' => $solution,
      'eif_solution_type' => $solution_type,
      'eif_description' => [
        'value' => 'This solution provides information sharing protocols that support European interoperability governance.',
        'format' => 'basic_html',
      ],
    ];

    // Add optional EIF fields if terms are available (loaded by URI).
    $layer = $this->rdfSyncMapper->getEntityByUri('http://data.europa.eu/IL1');
    if ($layer) {
      $eif_values['eif_layer'] = $layer;
    }

    $principle = $this->rdfSyncMapper->getEntityByUri('http://data.europa.eu/UP1');
    if ($principle) {
      $eif_values['eif_principle'] = $principle;
    }

    if ($recommendation) {
      $eif_values['eif_recommendation'] = [$recommendation];
    }

    $eif = $this->entityTypeManager->getStorage('meta_entity')->create($eif_values);
    $eif->save();
  }

}
