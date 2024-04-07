<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\eif\EifInterface;
use Drupal\eif\EifWrapperInterface;
use Drupal\eif\Entity\EifWrapper;
use Drupal\joinup\Traits\EntityReferenceTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\TopicTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\rdf_meta_entity\Entity\RdfMetaEntity;

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
    $owner = $this->nodeCreate((object) [
      'type' => 'owner',
      'owner_name' => 'Owner',
    ]);

    $contact = Rdf::create([
      'rid' => 'contact_information',
      'id' => 'http://example.com/contact',
      'field_contact_name' => 'Contact name',
      'field_contact_email' => 'contact.email@example.com',
    ]);
    $contact->save();
    $this->entities['rdf_entity'][$contact->id()] = $contact;

    // Create the NIFO sample collection.
    $collection = Rdf::create([
      'rid' => 'collection',
      'id' => 'http://nifo.collection',
      'label' => 'NIFO collection',
      'field_ar_state' => 'published',
      'field_topic' => $tid = $this->getRandomTopic()->id(),
      'field_ar_owner' => $owner->nid,
      'field_ar_contact_information' => 'http://example.com/contact',
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
      'field_is_owner' => $owner->nid,
      'field_is_contact_information' => 'http://example.com/contact',
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
    $qualifiers = [];
    $eif_perspective_fields = [];
    foreach ($table_data->getRows() as $row) {
      switch ($row[0]) {
        case 'Description':
          $eif_perspective_fields['field_eif_description'] = trim($row[1]);
          break;

        case 'Solution type':
          $eif_perspective_fields['field_eif_solution_type'] = $this->explodeCommaSeparatedStepArgument($row[1]);
          break;

        case 'Layer':
        case 'Model':
        case 'Principle':
          $eif_perspective_fields['eif_' . strtolower($row[0])] = $this->explodeCommaSeparatedStepArgument($row[1]);
          break;

        case 'Recommendation':
          $bundle = strtolower($row[0]);
          // The following will split a phrase in the format of:
          // name: Some title without comma, description: Some description.
          //
          // The part from the comma symbol on (the description) is optional.
          preg_match_all('/name:(\s*?)(?<name>[^,]*)(?:,(\s*?)description:(\s*?)(?<description>.*$))?/', $row[1], $matches);
          if (isset($matches['name'][0])) {
            $values = ['term' => trim($matches['name'][0])];
            if (isset($matches['description'][0])) {
              $values['description'] = trim($matches['description'][0]);
            }

            $qualifiers['eif_recommendation'][] = $this->createEifWrapper($bundle, $values);
          }
          break;
      }
    }

    $eif_perspective_fields = $this->convertEntityReferencesValues('rdf_meta_entity', 'eif_perspective', $eif_perspective_fields);
    $eif_perspective = RdfMetaEntity::create([
      'type' => 'eif_perspective',
      'target' => $solution,
    ] + $eif_perspective_fields + $qualifiers);
    $eif_perspective->save();
    $this->entities['rdf_meta_entity'][$eif_perspective->id()] = $eif_perspective;
  }

  /**
   * Creates an EIF wrapper entity and returns it. Does not save the entity.
   *
   * @param string $bundle
   *   The bundle of the EIF wrapper.
   * @param array $values
   *   An array of values of the EIF wrapper.
   *
   * @return \Drupal\eif\EifWrapperInterface
   *   The created EIF wrapper.
   */
  protected function createEifWrapper(string $bundle, array $values): EifWrapperInterface {
    $values = $this->convertEntityReferencesValues('eif_wrapper', $bundle, $values);
    $values['type'] = $bundle;
    $eif_wrapper = EifWrapper::create($values);
    $eif_wrapper->save();
    $this->entities['eif_wrapper'][$eif_wrapper->id()] = $eif_wrapper;
    return $eif_wrapper;
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
    if (empty(\Drupal::getContainer()->get('rdf_meta_entity.repository')->getMetaEntityForEntity($solution, 'eif_perspective'))) {
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
