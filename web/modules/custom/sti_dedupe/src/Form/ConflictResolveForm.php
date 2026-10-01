<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\sti_dedupe\Claims\ResolutionReconciler;
use Drupal\sti_dedupe\Claims\ResolutionWriter;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\Exception\StaleResolutionException;
use Drupal\sti_dedupe\Prefixes;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A curator's ruling on mutually exclusive claims about one property.
 *
 * One row per value currently claimed, each needing a verdict; exactly one
 * must be authoritative. Values that were claimed when an earlier ruling was
 * made, and no longer are, are shown read-only as history.
 */
final class ConflictResolveForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ResolutionWriter $writer,
    private readonly ResolutionReconciler $reconciler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('sti_dedupe.resolution_writer'),
      $container->get('sti_dedupe.resolution_reconciler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sti_dedupe_conflict_resolve_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ParagraphInterface $paragraph = NULL): array {
    [$innovation, $resolution] = $this->load($paragraph);
    $form_state->set('innovation', (int) $innovation->id());
    $form_state->set('resolution', (int) $resolution->id());

    $form['context'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Sources of %innovation disagree on %property, which takes a single value. Status: %status.', [
        '%innovation' => $innovation->label(),
        '%property' => Prefixes::compact((string) $resolution->get(ContentModel::RESOLUTION_PROPERTY)->value),
        '%status' => $resolution->get(ContentModel::RESOLUTION_STATUS)->value,
      ]),
    ];

    $form['rulings'] = [
      '#type' => 'table',
      '#caption' => $this->t('Currently claimed values'),
      '#header' => [$this->t('Value'), $this->t('Claimed by'), $this->t('Verdict')],
      '#tree' => TRUE,
    ];
    $options = [
      ContentModel::AUTHORITATIVE => $this->t('Authoritative'),
      ContentModel::SUPPLEMENTARY => $this->t('Supplementary'),
      ContentModel::REJECTED => $this->t('Rejected'),
    ];
    $current = $this->writer->currentVerdicts($resolution);
    // Rows are keyed by position: claim keys can contain any character a
    // literal value can, which is not safe in a form element name.
    $form_state->set('keys', array_keys($current));
    foreach (array_values($current) as $index => $verdict) {
      $previous = (string) $verdict->get(ContentModel::VERDICT_VALUE)->value;
      $form['rulings'][$index] = [
        'value' => ['#plain_text' => (string) $verdict->get(ContentModel::VERDICT_VALUE_LABEL)->value],
        'collection' => ['#plain_text' => $this->referencedLabel($verdict, ContentModel::VERDICT_COLLECTION)],
        'verdict' => [
          '#type' => 'radios',
          '#title' => $this->t('Verdict'),
          '#title_display' => 'invisible',
          '#options' => $options,
          '#default_value' => isset($options[$previous]) ? $previous : NULL,
          '#required' => TRUE,
        ],
      ];
    }

    $history = [];
    foreach ($resolution->get(ContentModel::VERDICTS)->referencedEntities() as $verdict) {
      if (!isset($current[$this->reconciler->verdictKey($verdict)])) {
        $history[] = $this->t('@value (@collection): @verdict, no longer claimed', [
          '@value' => $verdict->get(ContentModel::VERDICT_VALUE_LABEL)->value,
          '@collection' => $this->referencedLabel($verdict, ContentModel::VERDICT_COLLECTION),
          '@verdict' => $verdict->get(ContentModel::VERDICT_VALUE)->value,
        ]);
      }
    }
    if ($history) {
      $form['history'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Earlier ruling'),
        '#items' => $history,
      ];
    }

    $form['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reasoning'),
      '#default_value' => (string) $resolution->get(ContentModel::RESOLUTION_NOTE)->value,
      '#rows' => 3,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save ruling'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    try {
      ResolutionWriter::validate($this->rulings($form_state));
    }
    catch (\InvalidArgumentException $e) {
      $form_state->setErrorByName('rulings', $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $innovation = $this->entityTypeManager->getStorage('node')->load($form_state->get('innovation'));
    if (!$innovation instanceof NodeInterface) {
      throw new NotFoundHttpException();
    }
    try {
      $this->writer->resolve(
        $innovation,
        (int) $form_state->get('resolution'),
        $this->rulings($form_state),
        (string) $form_state->getValue('note'),
        $this->currentUser(),
      );
    }
    catch (StaleResolutionException $e) {
      $this->messenger()->addError($e->getMessage());
      $form_state->setRebuild();
      return;
    }
    $this->messenger()->addStatus($this->t('Ruling saved.'));
    $form_state->setRedirectUrl(Url::fromRoute('sti_dedupe.conflicts'));
  }

  /**
   * Returns the submitted verdict per claim key.
   *
   * @return array<string, string>
   *   Verdicts keyed by ClaimTuple key.
   */
  private function rulings(FormStateInterface $form_state): array {
    $keys = (array) $form_state->get('keys');
    $rulings = [];
    foreach ((array) $form_state->getValue('rulings') as $index => $row) {
      if (isset($keys[$index])) {
        $rulings[(string) $keys[$index]] = (string) ($row['verdict'] ?? '');
      }
    }
    return $rulings;
  }

  /**
   * Loads the innovation and checks the paragraph is one of its resolutions.
   *
   * @return array{0: \Drupal\node\NodeInterface, 1: \Drupal\paragraphs\ParagraphInterface}
   *   The innovation and the resolution as held by it.
   */
  private function load(?ParagraphInterface $paragraph): array {
    if (!$paragraph || $paragraph->bundle() !== ContentModel::RESOLUTION) {
      throw new NotFoundHttpException();
    }
    $innovation = $paragraph->getParentEntity();
    if (!$innovation instanceof NodeInterface || !$innovation->hasField(ContentModel::RESOLUTIONS)) {
      throw new NotFoundHttpException();
    }
    // Work on the instance the innovation holds, so a later save carries it.
    foreach ($innovation->get(ContentModel::RESOLUTIONS)->referencedEntities() as $resolution) {
      if ((int) $resolution->id() === (int) $paragraph->id()) {
        return [$innovation, $resolution];
      }
    }
    throw new NotFoundHttpException();
  }

  /**
   * Returns the label of a verdict's referenced entity, or a dash.
   */
  private function referencedLabel(ParagraphInterface $verdict, string $field): string {
    $entity = $verdict->get($field)->entity;
    return $entity ? (string) $entity->label() : '—';
  }

}
