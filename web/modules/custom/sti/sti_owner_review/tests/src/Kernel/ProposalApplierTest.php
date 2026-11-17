<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_owner_review\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\sti_owner_review\ProposalApplier;

/**
 * The failures here are silent: a rejected AI value written anyway, a curated value overwritten, a proposal
 * offered again after rejection, a parent cycle saved.
 *
 * @coversDefaultClass \Drupal\sti_owner_review\ProposalApplier
 * @group sti_owner_review
 */
final class ProposalApplierTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'node', 'field', 'filter', 'text', 'link', 'sti_owner_review'];

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
      FieldConfig::create(['field_name' => $name, 'entity_type' => 'node', 'bundle' => 'owner', 'label' => $name])->save();
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

    $fao->set(ProposalApplier::PARENT_FIELD, $soil->id());
    $this->assertCount(1, $fao->get(ProposalApplier::PARENT_FIELD)->validate());
    $fao->set(ProposalApplier::PARENT_FIELD, $fao->id());
    $this->assertCount(1, $fao->get(ProposalApplier::PARENT_FIELD)->validate());
  }

}
