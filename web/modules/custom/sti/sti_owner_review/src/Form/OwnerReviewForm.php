<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\import_ird_jsons\Resolver\EntityCandidates;
use Drupal\node\NodeInterface;
use Drupal\sti_owner_review\ProposalApplier;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Before/after of an owner's AI proposal, accepted or rejected field by field.
 */
final class OwnerReviewForm extends FormBase {

  private const CREATE = '_create';

  public function __construct(
    private readonly ProposalApplier $applier,
    private readonly EntityCandidates $candidates,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('sti_owner_review.applier'), $container->get('import_ird_jsons.entity_candidates'));
  }

  public function getFormId(): string {
    return 'sti_owner_review_form';
  }

  /**
   * The tab is there only for owners with a pending proposal.
   */
  public static function access(NodeInterface $node): AccessResultInterface {
    return AccessResult::allowedIf($node->bundle() === 'owner' && ProposalApplier::isPending($node))->addCacheableDependency($node);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $owner = $node;
    $form_state->set('owner', $owner);
    $proposal = ProposalApplier::proposal($owner);
    if (!ProposalApplier::isPending($owner)) {
      $form['none']['#markup'] = $this->t('@owner has no pending AI proposal.', ['@owner' => $owner->label()]);
      return $form;
    }
    $searchOnly = ($proposal['source_type'] ?? '') === 'search_grounded';

    $form['owner']['#markup'] = '<h2>' . $owner->toLink()->toString() . '</h2>';
    $form['meta'] = [
      '#theme' => 'item_list',
      '#items' => array_filter([
        $this->t('Source: @s, @model, @date', ['@s' => $proposal['source_type'] ?? '?', '@model' => $proposal['model'] ?? '?', '@date' => $proposal['date'] ?? '?']),
        isset($proposal['evidence_url']) ? ['#type' => 'link', '#title' => $this->t('Evidence: @url', ['@url' => $proposal['evidence_url']]), '#url' => Url::fromUri($proposal['evidence_url']), '#attributes' => ['target' => '_blank']] : NULL,
        $searchOnly ? $this->t('No page about this organisation was found and checked: the description comes from web search alone and may describe a namesake.') : NULL,
      ]),
    ];
    if (!empty($proposal['rejected_websites'])) {
      $form['rejected'] = [
        '#type' => 'details',
        '#title' => $this->t('Pages tried and rejected (@n)', ['@n' => count($proposal['rejected_websites'])]),
        'list' => [
          '#theme' => 'item_list',
          '#items' => array_map(fn($r) => "{$r['url']}: {$r['reason']}", $proposal['rejected_websites']),
        ],
      ];
    }

    $form['fields'] = [
      '#type' => 'table',
      '#header' => [$this->t('Accept'), $this->t('Field'), $this->t('Current'), $this->t('Proposed')],
    ];
    foreach ($proposal['fields'] as $key => $proposed) {
      $field = ProposalApplier::TARGETS[$key] ?? NULL;
      if ($field === NULL || !$owner->hasField($field)) {
        continue;
      }
      $form['fields'][$key] = [
        'accept' => ['#type' => 'checkbox', '#default_value' => !($key === 'description' && $searchOnly)],
        'field' => ['#plain_text' => (string) $owner->get($field)->getFieldDefinition()->getLabel()],
        'current' => ['#plain_text' => $this->current($owner, $key) ?: '—'],
        'proposed' => $key === 'parent' ? $this->parentChoice($owner, (string) $proposed) : ['#plain_text' => (string) $proposed],
      ];
      if ($key === 'parent') {
        $form['fields'][$key]['accept']['#default_value'] = isset($form['fields'][$key]['proposed']['#default_value']);
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['selected'] = ['#type' => 'submit', '#name' => 'selected', '#value' => $this->t('Accept selected'), '#button_type' => 'primary'];
    $form['actions']['all'] = ['#type' => 'submit', '#name' => 'all', '#value' => $this->t('Accept all')];
    $form['actions']['none'] = ['#type' => 'submit', '#name' => 'none', '#value' => $this->t('Reject all')];
    $form['actions']['delete'] = ['#type' => 'submit', '#name' => 'delete', '#value' => $this->t('Reject and request deletion')];
    return $form;
  }

  /**
   * The proposed parent: the existing owner it matches, close ones, or creating it as a new stub.
   */
  private function parentChoice(NodeInterface $owner, string $name): array {
    ['index' => $index, 'labels' => $labels] = $this->candidates->build('node', $owner->bundle());
    $result = $index->resolve($name);
    $ids = $result->id !== NULL ? [$result->id] : array_slice(array_keys($result->candidates), 0, 5);
    $options = [];
    foreach (array_diff($ids, [(string) $owner->id()]) as $id) {
      $options[$id] = $this->t('Existing owner: @label', ['@label' => $labels[$id]]);
    }
    $options[self::CREATE] = $this->t('Not in the system: create “@name” as a new owner (a stub, reviewed on its own)', ['@name' => $name]);
    return [
      '#type' => 'radios',
      '#title' => $this->t('Proposed: @name', ['@name' => $name]),
      '#options' => $options,
      '#default_value' => $result->id !== NULL && $result->id !== (string) $owner->id() ? $result->id : NULL,
    ];
  }

  private function current(NodeInterface $owner, string $key): string {
    $item = $owner->get(ProposalApplier::TARGETS[$key])->first();
    return match ($key) {
      'webpage' => (string) $item?->uri,
      'description' => (string) $item?->value,
      'parent' => (string) $item?->entity?->label(),
    };
  }

  /**
   * @return string[]
   *   The proposal keys accepted by the button pressed.
   */
  private function acceptedKeys(FormStateInterface $form_state): array {
    $button = $form_state->getTriggeringElement()['#name'] ?? '';
    $rows = (array) $form_state->getValue('fields');
    return match ($button) {
      'all' => array_keys($rows),
      'selected' => array_keys(array_filter($rows, fn($row) => !empty($row['accept']))),
      default => [],
    };
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (in_array('parent', $this->acceptedKeys($form_state), TRUE) && !$form_state->getValue(['fields', 'parent', 'proposed'])) {
      $form_state->setErrorByName('fields][parent][proposed', $this->t('Choose which owner the parent is, or leave the parent unaccepted.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\node\NodeInterface $owner */
    $owner = $form_state->get('owner');
    $button = $form_state->getTriggeringElement()['#name'] ?? '';
    $parent = (string) $form_state->getValue(['fields', 'parent', 'proposed']);
    try {
      if ($button === 'none' || $button === 'delete') {
        $this->applier->reject($owner, $button === 'delete');
        $this->messenger()->addStatus($this->t('Proposal for @owner rejected.', ['@owner' => $owner->label()]));
      }
      else {
        $this->applier->accept($owner, $this->acceptedKeys($form_state),
          $parent !== '' && $parent !== self::CREATE ? $parent : NULL, $parent === self::CREATE);
        $this->messenger()->addStatus($this->t('@owner reviewed.', ['@owner' => $owner->label()]));
      }
    }
    catch (\InvalidArgumentException | \RuntimeException $e) {
      $this->messenger()->addError($e->getMessage());
      return;
    }
    // Back to the list the curator came from (?destination, set by the operations link), else to the owner.
    $form_state->setRedirect('entity.node.canonical', ['node' => $owner->id()]);
  }

}
