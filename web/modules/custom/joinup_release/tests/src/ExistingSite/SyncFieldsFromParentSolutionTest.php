<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_release\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests field synchronization between solution and release.
 *
 * @todo This should be converted to check the revision reference or dropped.
 *
 * @group joinup_release
 */
class SyncFieldsFromParentSolutionTest extends JoinupExistingSiteTestBase {

  /**
   * Testing terms.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms = [];

  /**
   * Testing entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $entities = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = Vocabulary::load('eira');

    // Create two solution type terms.
    $this->terms['eira_t1'] = $this->createTerm($vocabulary, [
      'uri' => 'http://example.com/eira/t1',
      'name' => 'Type 1',
    ]);
    $this->terms['eira_t2'] = $this->createTerm($vocabulary, [
      'uri' => 'http://example.com/eira/t2',
      'name' => 'Type 2',
    ]);

    // Create two topic terms.
    $vocabulary = Vocabulary::load('topic');
    $this->terms['topic_d1'] = $this->createTerm($vocabulary, [
      'uri' => 'http://example.com/topic/d1',
      'name' => 'Domain 1',
    ]);
    $this->terms['topic_d2'] = $this->createTerm($vocabulary, [
      'uri' => 'http://example.com/topic/d2',
      'name' => 'Domain 2',
    ]);

    // Create two contact information entities.
    $this->entities['contact_information'][] = $this->createNode([
      'type' => 'contact_information',
      'title' => 'Contact 1',
    ]);
    $this->entities['contact_information'][] = $this->createNode([
      'type' => 'contact_information',
      'title' => 'Contact 2',
    ]);

    // Create two owner entities.
    $this->entities['owner'][] = $this->createNode([
      'type' => 'owner',
      'title' => 'Owner 1',
      'field_state' => 'published',
    ]);
    $this->entities['owner'][] = $this->createNode([
      'type' => 'owner',
      'title' => 'Owner 2',
      'field_state' => 'published',
    ]);

    $this->createRdfEntity([
      'rid' => 'collection',
      'id' => 'http://example.com/collection',
      'field_ar_state' => 'published',
      'label' => 'Collection',
    ]);

    // Create two solutions to be related.
    $this->createRdfEntity([
      'rid' => 'solution',
      'id' => 'http://example.com/solution-related1',
      'label' => 'Related solution 1',
      'field_is_state' => 'proposed',
      'collection' => 'http://example.com/collection',
    ]);
    $this->createRdfEntity([
      'rid' => 'solution',
      'id' => 'http://example.com/solution-related2',
      'label' => 'Related solution 2',
      'field_is_state' => 'proposed',
      'collection' => 'http://example.com/collection',
    ]);
  }

  /**
   * Tests field synchronization between solution and release.
   */
  public function testSyncFieldsFromParentSolution() {
    // Create a solution.
    $this->createRdfEntity([
      'rid' => 'solution',
      'id' => 'http://example.com/solution',
      'label' => 'Solution',
      'field_is_state' => 'published',
      // Synchronized fields.
      'field_is_description' => 'init description',
      'field_is_solution_type' => $this->terms['eira_t1'],
      'field_is_contact_information' => $this->entities['contact_information'][0]->id(),
      'field_is_owner' => $this->entities['owner'][0]->id(),
      'field_is_related_solutions' => 'http://example.com/solution-related1',
      'field_is_included_asset' => 'http://example.com/solution-related1',
      'field_is_translation' => 'http://example.com/solution-related1',
      'field_topic' => $this->terms['topic_d1'],
      'collection' => 'http://example.com/collection',
    ]);

    // Create a release.
    $release = $this->createRdfEntity([
      'rid' => 'asset_release',
      'id' => 'http://example.com/release',
      'label' => 'release',
      'field_isr_is_version_of' => 'http://example.com/solution',
    ]);

    // Check that the release fields were synchronized.
    $this->assertSame('init description', $release->field_isr_description->value);
    $this->assertSame('http://example.com/eira/t1', $this->terms['eira_t1']->get('uri')->value);
    $this->assertSame($this->entities['owner'][0]->id(), $release->field_isr_owner->target_id);
    $this->assertSame($this->entities['contact_information'][0]->id(), $release->field_isr_contact_information->target_id);
    $this->assertSame('http://example.com/solution-related1', $release->field_isr_related_solutions->target_id);
    $this->assertSame('http://example.com/solution-related1', $release->field_isr_included_asset->target_id);
    $this->assertSame('http://example.com/solution-related1', $release->field_isr_translation->target_id);
    $this->assertSame('http://example.com/topic/d1', $this->terms['topic_d1']->get('uri')->value);

    // Change the values of solution fields but reload the solution first, to
    // get the last value of 'field_is_has_version' field.
    Rdf::load('http://example.com/solution')
      ->set('field_is_description', 'changed description')
      ->set('field_is_solution_type', $this->terms['eira_t2'])
      ->set('field_is_owner', $this->entities['owner'][1]->id())
      ->set('field_is_contact_information', $this->entities['contact_information'][1]->id())
      ->set('field_is_related_solutions', 'http://example.com/solution-related2')
      ->set('field_is_included_asset', 'http://example.com/solution-related2')
      ->set('field_is_translation', 'http://example.com/solution-related2')
      ->set('field_topic', $this->terms['topic_d2'])
      ->save();

    // Reload the release.
    $release = Rdf::load('http://example.com/release');
    // Check that the release fields were synchronized.
    $this->assertSame('changed description', $release->field_isr_description->value);
    $this->assertSame('http://example.com/eira/t2', $this->terms['eira_t2']->get('uri')->value);
    $this->assertSame($this->entities['owner'][1]->id(), $release->field_isr_owner->target_id);
    $this->assertSame($this->entities['contact_information'][1]->id(), $release->field_isr_contact_information->target_id);
    $this->assertSame('http://example.com/solution-related2', $release->field_isr_related_solutions->target_id);
    $this->assertSame('http://example.com/solution-related2', $release->field_isr_included_asset->target_id);
    $this->assertSame('http://example.com/solution-related2', $release->field_isr_translation->target_id);
    $this->assertSame('http://example.com/topic/d2', $this->terms['topic_d2']->get('uri')->value);
  }

}
