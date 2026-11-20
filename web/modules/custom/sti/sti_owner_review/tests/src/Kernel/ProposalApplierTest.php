<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_owner_review\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\sti_owner_review\Plugin\Validation\Constraint\NoParentCycleConstraint;
use Drupal\sti_owner_review\ProposalApplier;
use Drupal\sti_owner_review\QualityChecks;
use Drupal\sti_owner_review\RecordStatus;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The failures here are silent: a rejected AI value written anyway, a curated value overwritten, a proposal
 * offered again after rejection, a parent cycle saved.
 */
#[CoversClass(ProposalApplier::class)]
#[Group('sti_owner_review')]
#[RunTestsInSeparateProcesses]
final class ProposalApplierTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'node', 'field', 'filter', 'text', 'link', 'file', 'taxonomy',
    'entity_reference_revisions', 'paragraphs', 'sti_owner_review'];

  private ProposalApplier $applier;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'filter']);
    NodeType::create(['type' => 'owner', 'name' => 'Owner'])->save();
    foreach ([
      'webpage' => ['link', []],
      'description' => ['text_long', []],
      'field_state' => ['string', []],
      ProposalApplier::PARENT_FIELD => ['entity_reference', ['target_type' => 'node']],
    ] as $name => [$type, $settings]) {
      FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'node', 'type' => $type, 'settings' => $settings])->save();
      FieldConfig::create(['field_name' => $name, 'entity_type' => 'node', 'bundle' => 'owner', 'label' => $name,
        'required' => in_array($name, ['webpage', 'description'], TRUE)])->save();
    }
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('taxonomy_term');
    ParagraphsType::create(['id' => 'quality_check', 'label' => 'Quality check'])->save();
    foreach ([
      'field_ai_generated' => ['boolean', []],
      'field_content_type_machine_name' => ['string', []],
      'field_quality_assessment' => ['entity_reference', ['target_type' => 'taxonomy_term']],
    ] as $name => [$type, $settings]) {
      FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'paragraph', 'type' => $type, 'settings' => $settings])->save();
      FieldConfig::create(['field_name' => $name, 'entity_type' => 'paragraph', 'bundle' => 'quality_check', 'label' => $name])->save();
    }
    Vocabulary::create(['vid' => 'quality_assessment', 'name' => 'Quality assessment'])->save();
    foreach ([QualityChecks::ACCEPTED, QualityChecks::EDITED] as $name) {
      Term::create(['vid' => 'quality_assessment', 'name' => $name])->save();
    }
    Vocabulary::create(['vid' => 'sti_record_status', 'name' => 'Record status'])->save();
    foreach ([RecordStatus::AI, RecordStatus::AI_INCOMPLETE, RecordStatus::REJECTED, RecordStatus::INCOMPLETE, RecordStatus::APPROVED, RecordStatus::CURATED] as $name) {
      Term::create(['vid' => 'sti_record_status', 'name' => $name])->save();
    }
    $this->installConfig(['sti_owner_review']);
    $this->applier = $this->container->get('sti_owner_review.applier');
  }

  private function owner(string $title, array $proposed = [], array $values = []): NodeInterface {
    $owner = Node::create(['type' => 'owner', 'title' => $title, 'status' => 0, 'field_state' => 'needs_update'] + $values);
    if ($proposed) {
      $owner->set(ProposalApplier::FIELD, json_encode(['status' => 'pending', 'fields' => $proposed, 'model' => 'test']));
    }
    $owner->save();
    return $owner;
  }

  private function reload(NodeInterface $node): NodeInterface {
    return $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($node->id());
  }

  /**
   * Only the accepted field is written; the rejected one keeps its curated value.
   */
  public function testAcceptSelectedWritesOnlyThose(): void {
    $owner = $this->owner('Plan Conservation', ['webpage' => 'https://plan.example', 'description' => 'AI text'],
      ['description' => ['value' => 'Curated text', 'format' => 'plain_text']]);
    $this->applier->accept($owner, ['webpage']);

    $saved = $this->reload($owner);
    $this->assertSame('https://plan.example', $saved->get('webpage')->uri);
    $this->assertSame('Curated text', $saved->get('description')->value);
    $this->assertTrue($saved->isPublished());
    $this->assertSame('published', $saved->get('field_state')->value);
    $proposal = ProposalApplier::proposal($saved);
    $this->assertSame(['reviewed', ['webpage'], ['description']], [$proposal['status'], $proposal['accepted'], $proposal['rejected']]);
  }

  /**
   * Rejecting changes nothing but the proposal's status, so the queue and the next enrichment skip it.
   */
  public function testRejectChangesOnlyTheStatus(): void {
    $owner = $this->owner('Soil Doctors', ['webpage' => 'https://wrong.example']);
    $this->applier->reject($owner);

    $saved = $this->reload($owner);
    $this->assertTrue($saved->get('webpage')->isEmpty());
    $this->assertFalse($saved->isPublished());
    $this->assertSame('needs_update', $saved->get('field_state')->value);
    $this->assertFalse(ProposalApplier::isPending($saved));

    $this->expectException(\InvalidArgumentException::class);
    $this->applier->accept($saved, ['webpage']);
  }

  /**
   * A parent not in the system is created once, as an unpublished stub awaiting its own review.
   */
  public function testNewParentIsCreatedAsOneStub(): void {
    $owner = $this->owner('Soil Doctors', ['parent' => 'Food and Agriculture Organization']);
    $this->applier->accept($owner, ['parent'], NULL, TRUE);

    $parent = $this->reload($owner)->get(ProposalApplier::PARENT_FIELD)->entity;
    $this->assertSame('Food and Agriculture Organization', $parent->label());
    $this->assertFalse($parent->isPublished());
    $this->assertSame('needs_update', $parent->get('field_state')->value);
    $this->assertSame(2, (int) \Drupal::entityQuery('node')->accessCheck(FALSE)->count()->execute());
  }

  /**
   * A parent that would loop is refused before anything is saved; a manual edit is refused by validation.
   */
  public function testParentCycleIsRefused(): void {
    $fao = $this->owner('FAO', ['parent' => 'Soil Doctors']);
    $soil = $this->owner('Soil Doctors', [], [ProposalApplier::PARENT_FIELD => $fao->id()]);

    try {
      $this->applier->accept($fao, ['parent'], (string) $soil->id());
      $this->fail('A cycle was accepted.');
    }
    catch (\InvalidArgumentException) {
    }
    $this->assertTrue(ProposalApplier::isPending($this->reload($fao)));
    $this->assertTrue($this->reload($fao)->get(ProposalApplier::PARENT_FIELD)->isEmpty());

    // Counts only cycle violations: core's reference check also objects to the unpublished test owners.
    $cycles = fn() => count(array_filter(iterator_to_array($fao->get(ProposalApplier::PARENT_FIELD)->validate()),
      fn($v) => $v->getConstraint() instanceof NoParentCycleConstraint));
    $fao->set(ProposalApplier::PARENT_FIELD, $soil->id());
    $this->assertSame(1, $cycles());
    $fao->set(ProposalApplier::PARENT_FIELD, $fao->id());
    $this->assertSame(1, $cycles());
  }

  /**
   * @return array<string, array{ai: bool, assessment: string}>
   *   The owner's quality checks by field.
   */
  private function checks(NodeInterface $owner): array {
    $out = [];
    foreach ($this->reload($owner)->get(QualityChecks::HOST) as $item) {
      $out[$item->entity->get('field_content_type_machine_name')->value] = [
        'ai' => (bool) $item->entity->get('field_ai_generated')->value,
        'assessment' => (string) $item->entity->get('field_quality_assessment')->entity?->label(),
      ];
    }
    return $out;
  }

  /**
   * Accepted fields are flagged as AI-generated, once each; rejected ones are not flagged.
   */
  public function testAcceptedFieldsAreFlaggedAsAi(): void {
    $owner = $this->owner('Plan Conservation', ['webpage' => 'https://plan.example', 'description' => 'AI text']);
    $this->applier->accept($owner, ['webpage']);
    $this->assertSame(['webpage' => ['ai' => TRUE, 'assessment' => QualityChecks::ACCEPTED]], $this->checks($owner));

    $rejected = $this->owner('Soil Doctors', ['webpage' => 'https://wrong.example']);
    $this->applier->reject($rejected);
    $this->assertSame([], $this->checks($rejected));
  }

  /**
   * A later change clears the AI flag and marks it edited, whether a curator or an import makes it: imported
   * content counts as people's work.
   */
  public function testChangesClearFlagAndMarkEdited(): void {
    $owner = $this->owner('Plan Conservation', ['webpage' => 'https://plan.example', 'description' => 'AI text']);
    $this->applier->accept($owner, ['webpage', 'description']);

    $synced = $this->reload($owner);
    $synced->set('description', ['value' => 'Re-imported', 'format' => 'plain_text']);
    $synced->setSyncing(TRUE);
    $synced->save();
    $this->assertSame(['ai' => FALSE, 'assessment' => QualityChecks::EDITED], $this->checks($owner)['description']);
    $this->assertSame(['ai' => TRUE, 'assessment' => QualityChecks::ACCEPTED], $this->checks($owner)['webpage']);

    $edited = $this->reload($owner);
    $edited->set('webpage', ['uri' => 'https://plan.example/about']);
    $edited->save();
    $this->assertSame(['ai' => FALSE, 'assessment' => QualityChecks::EDITED], $this->checks($owner)['webpage']);
  }

  private function recordStatus(NodeInterface $owner): string {
    return (string) $this->reload($owner)->get(RecordStatus::FIELD)->entity?->label();
  }

  /**
   * Each rule of RecordStatus::derive(), in the order they apply: what the review list and the pull rely on.
   */
  public function testRecordStatusIsDerivedFromTheData(): void {
    // A pending proposal: AI-enriched if it would fill every required field, else still incomplete.
    $full = $this->owner('Full', ['webpage' => 'https://full.example', 'description' => 'AI text']);
    $partial = $this->owner('Partial', ['webpage' => 'https://partial.example']);
    $this->assertSame(RecordStatus::AI, $this->recordStatus($full));
    $this->assertSame(RecordStatus::AI_INCOMPLETE, $this->recordStatus($partial));

    // Accepted: approved when complete, incomplete while a required field is still empty.
    $this->applier->accept($full, ['webpage', 'description']);
    $this->applier->accept($partial, ['webpage']);
    $this->assertSame(RecordStatus::APPROVED, $this->recordStatus($full));
    $this->assertSame(RecordStatus::INCOMPLETE, $this->recordStatus($partial));

    // Rejected while incomplete.
    $rejected = $this->owner('Rejected', ['webpage' => 'https://wrong.example']);
    $this->applier->reject($rejected);
    $this->assertSame(RecordStatus::REJECTED, $this->recordStatus($rejected));

    // People replace the AI values: approved while one is left, manually curated when none is.
    $this->reload($full)->set('webpage', ['uri' => 'https://full.example/about'])->save();
    $this->assertSame(RecordStatus::APPROVED, $this->recordStatus($full));
    $this->reload($full)->set('description', ['value' => 'Written by a curator.', 'format' => 'plain_text'])->save();
    $this->assertSame(RecordStatus::CURATED, $this->recordStatus($full));

    // Without any AI: complete is manually curated, a missing required field is incomplete.
    $human = $this->publishedOwner('Human', ['webpage' => ['uri' => 'https://h.example'], 'description' => ['value' => 'Text.', 'format' => 'plain_text']]);
    $missing = $this->publishedOwner('Missing', ['webpage' => ['uri' => 'https://m.example']]);
    $this->assertSame(RecordStatus::CURATED, $this->recordStatus($human));
    $this->assertSame(RecordStatus::INCOMPLETE, $this->recordStatus($missing));
  }

  /**
   * Bulk accept takes only what needs no judgement: no proposal and a proposed parent are skipped, untouched.
   */
  public function testBulkAcceptSkipsWhatNeedsJudgement(): void {
    $plain = $this->owner('Plan Conservation', ['webpage' => 'https://plan.example']);
    $withParent = $this->owner('Soil Doctors', ['webpage' => 'https://soil.example', 'parent' => 'FAO']);
    $without = $this->owner('Industry investors');

    $this->container->get('plugin.manager.action')->createInstance('sti_owner_review_accept')
      ->executeMultiple([$plain, $withParent, $without]);

    $this->assertTrue($this->reload($plain)->isPublished());
    $this->assertTrue(ProposalApplier::isPending($this->reload($withParent)));
    $this->assertTrue($this->reload($withParent)->get('webpage')->isEmpty());
    $this->assertFalse($this->reload($without)->isPublished());
  }

  private function publishedOwner(string $title, array $values): NodeInterface {
    $owner = Node::create(['type' => 'owner', 'title' => $title, 'status' => 1, 'field_state' => 'published'] + $values);
    $owner->save();
    return $owner;
  }

}
