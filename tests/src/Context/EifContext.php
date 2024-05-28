<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\eif\EifInterface;
use Drupal\joinup\Traits\EntityReferenceTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\TopicTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\rdf_entity\Entity\Rdf;

/**
 * Behat step definitions and related methods provided by the eif module.
 */
class EifContext extends RawDrupalContext {

  use EntityReferenceTrait;
  use EntityTrait;
  use SearchTrait;
  use StringTranslationTrait;
  use TestingEntitiesTrait;
  use TopicTrait;
  use UtilityTrait;

  /**
   * Creates the standard 'EIF' solution and a random parent collection.
   *
   * @BeforeScenario @eif_community&&@api
   */
  public function setupEifData(BeforeScenarioScope $scope): void {
    // Create an owner.
    $owner = Rdf::create([
      'rid' => 'owner',
      'id' => 'http://example.com/owner',
      'field_owner_name' => 'Owner',
    ]);
    $owner->save();
    $this->entities['rdf_entity'][$owner->id()] = $owner;

    $contact = Node::create([
      'type' => 'contact_information',
      'title' => 'Contact name',
      'email' => 'contact.email@example.com',
    ]);
    $contact->save();
    $this->entities['node'][$contact->id()] = $contact;

    // Create the NIFO sample collection.
    $collection = Rdf::create([
      'rid' => 'collection',
      'id' => 'http://nifo.collection',
      'label' => 'NIFO collection',
      'field_ar_state' => 'published',
      'field_topic' => $tid = $this->getRandomTopic()->id(),
      'field_ar_owner' => 'http://example.com/owner',
      'field_ar_contact_information' => $contact->id(),
    ]);
    $collection->save();
    $this->entities['rdf_entity'][$collection->id()] = $collection;

    // Create the EIF Toolbox solution.
    $solution = Rdf::create([
      'rid' => 'solution',
      'id' => EifInterface::EIF_ID,
      'label' => 'EIF Toolbox',
      'collection' => 'http://nifo.collection',
      'field_is_state' => 'published',
      'field_topic' => $tid,
      'field_is_owner' => 'http://example.com/owner',
      'field_is_contact_information' => $contact->id(),
    ]);
    $solution->save();
    $this->entities['rdf_entity'][$solution->id()] = $solution;

    $page1 = Node::create([
      'type' => 'custom_page',
      'nid' => EifInterface::EIF_SOLUTIONS_NID,
      'title' => 'Solutions',
      'og_audience' => EifInterface::EIF_ID,
      'field_paragraphs_body' => Paragraph::create([
        'type' => 'simple_paragraph',
        'field_body' => [
          'value' => 'Currently available supporting solutions that can be used as added components that help build interoperability solutions.',
          'format' => 'content_editor',
        ],
      ]),
      'field_state' => 'published',
    ]);
    $page1->save();
    $this->entities['node'][$page1->id()] = $page1;

    $page2 = Node::create([
      'type' => 'custom_page',
      'nid' => EifInterface::EIF_ONLINE_ASSISTANT,
      'title' => 'Online Assistant',
      'og_audience' => EifInterface::EIF_ID,
      'field_paragraphs_body' => Paragraph::create([
        'type' => 'simple_paragraph',
        'field_body' => [
          'value' => '',
          'format' => 'content_editor',
        ],
      ]),
      'field_state' => 'published',
    ]);
    $page2->save();
    $this->entities['node'][$page2->id()] = $page2;

    // Ensure the taxonomy terms are indexed.
    $vids = [
      'eif_conceptual_model',
      'eif_interoperability_layer',
      'eif_principle',
      'eif_recommendation',
    ];

    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage('taxonomy_term');
    $tids = $storage->getQuery()->accessCheck(FALSE)->condition('vid', $vids, 'IN')->execute();
    /** @var \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager $content_tracking_manager */
    $content_tracking_manager = \Drupal::service('search_api.entity_datasource.tracking_manager');
    foreach ($storage->loadMultiple($tids) as $term) {
      $content_tracking_manager->trackEntityChange($term);
    }

    \Drupal::getContainer()->get('search_api.post_request_indexing')->destruct();
  }

  /**
   * Generates an EIF perspective entity for a solution.
   *
   * @param \Behat\Gherkin\Node\TableNode $table_data
   *   The table data.
   * @param string $solution
   *   The solution label.
   *
   * @Given the following EIF perspective data for the :solution solution:
   */
  public function givenEifPerspectiveOfSolution(TableNode $table_data, string $solution): void {
    $solution = $this->getEntityByLabel('rdf_entity', $solution, 'solution');
    $fields = [];
    foreach ($table_data->getRows() as $row) {
      $field_name = 'eif_' . str_replace(' ', '_', strtolower($row[0]));
      $fields[$field_name] ??= [];
      switch ($row[0]) {
        case 'Description':
          $value = [['value' => trim($row[1]), 'format' => 'content_editor']];
          break;

        case 'Layer':
        case 'Model':
        case 'Principle':
        case 'Solution type':
          $value = $this->explodeCommaSeparatedStepArgument($row[1]);
          break;

        case 'Recommendation':
          // The following will split a phrase in the format of:
          // @code
          // name: Some title without comma, description: Some description.
          // @endcode
          // The part from the comma symbol on (the description) is optional.
          preg_match('/name:(\s*?)(?<name>[^,]*)(?:,(\s*?)description:(\s*?)(?<description>.*$))?/', $row[1], $matches);
          $value = NULL;
          if (isset($matches['name'])) {
            $value = [['entity' => $this->getEntityByLabel('taxonomy_term', trim($matches['name']), 'eif_recommendation')]];
            if (isset($matches['description'])) {
              $value[0]['value'] = trim($matches['description']);
              $value[0]['format'] = 'basic_html';
            }
          }
          break;
      }
      $fields[$field_name] = [...$fields[$field_name], ...$value];
    }

    $fields = $this->convertEntityReferencesValues('meta_entity', 'eif_perspective', $fields);
    $eif_perspective = MetaEntity::create([
      'type' => 'eif_perspective',
      'target' => $solution,
    ] + $fields);
    $eif_perspective->save();
    $this->entities['meta_entity'][$eif_perspective->id()] = $eif_perspective;
  }

  /**
   * Navigates to the EIF perspective page of the given solution.
   *
   * @param string $solution
   *   The solution label.
   *
   * @Given I go to the EIF perspective page of the :solution solution
   */
  public function goToEifPerspectiveOfSolution(string $solution): void {
    $solution = $this->getEntityByLabel('rdf_entity', $solution, 'solution');
    $url = Url::fromRoute('entity.rdf_entity.eif_perspective', [
      'rdf_entity' => $solution->id(),
    ]);
    $this->visitPath($url->getInternalPath());
  }

  /**
   * Navigates to the EIF perspective edit form of the given solution.
   *
   * @param string $solution
   *   The solution label.
   *
   * @Given I go to the EIF perspective edit form of the :solution solution
   */
  public function goToEifEditFormOfSolution(string $solution): void {
    $solution = $this->getEntityByLabel('rdf_entity', $solution, 'solution');
    $url = Url::fromRoute('eif.eif_perspective_form', [
      'rdf_entity' => $solution->id(),
    ]);
    $this->visitPath($url->getInternalPath());
  }

  /**
   * Asserts that there is an EIF perspective entity associated to the solution.
   *
   * @param string $solution
   *   The solution label.
   *
   * @Given there should be an EIF perspective associated with the :solution solution
   */
  public function assertSolutionWithPerspective(string $solution): void {
    $solution = $this->getEntityByLabel('rdf_entity', $solution, 'solution');
    if (empty(\Drupal::getContainer()->get('meta_entity.repository')->getMetaEntityForEntity($solution, 'eif_perspective'))) {
      throw new \Exception('No perspective was found for the solution');
    }
  }

  /**
   * Deletes the EIF perspective of a solution.
   *
   * @param string $solution_label
   *   The solution label.
   *
   * @Given I delete the EIF perspective of the :solution_label solution
   */
  public function deletePerspectiveOfSolution(string $solution_label): void {
    $solution = $this->getEntityByLabel('rdf_entity', $solution_label, 'solution');
    $eif_perspective = $solution->get('eif_perspective')->entity;
    $eif_perspective->delete();
  }

}
