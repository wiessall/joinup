<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\InnovationLookup;
use Drupal\sti_dedupe\Linker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets an ATIO curator fold the innovations of two "same" records into one.
 *
 * Optional: the curator can leave the innovations apart. Merging is the only
 * place sti_dedupe changes an innovation's derived-from list.
 */
final class ReconcileForm extends FormBase {

  /**
   * Innovation ids on offer, from both records.
   *
   * @var int[]
   */
  private array $innovationIds = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly InnovationLookup $innovations,
    private readonly Linker $linker,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('sti_dedupe.innovation_lookup'),
      $container->get('sti_dedupe.linker'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sti_dedupe_reconcile_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $a = NULL, ?NodeInterface $b = NULL): array {
    $this->innovationIds = $a && $b ? $this->innovations->derivedFrom([(int) $a->id(), (int) $b->id()]) : [];
    if (count($this->innovationIds) < 2) {
      $form['none'] = ['#markup' => $this->t('These records are not on different innovations; there is nothing to reconcile.')];
      return $form;
    }

    $options = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($this->innovationIds) as $nid => $innovation) {
      $options[$nid] = $innovation->toLink()->toString();
    }
    $form['records'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('%a and %b were decided to be the same innovation, but are on different ATIO innovations.', [
        '%a' => $a->label(),
        '%b' => $b->label(),
      ]),
    ];
    $form['survivor'] = [
      '#type' => 'radios',
      '#title' => $this->t('Innovation that remains'),
      '#description' => $this->t('It takes over the sources of the others. The others are unpublished, not deleted, and their URIs redirect to it.'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['merge'] = [
      '#type' => 'submit',
      '#value' => $this->t('Merge innovations'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Leave them apart'),
      '#url' => Url::fromRoute('sti_dedupe.collections'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $survivor = (int) $form_state->getValue('survivor');
    $this->linker->mergeInnovations($survivor, array_diff($this->innovationIds, [$survivor]));
    $this->messenger()->addStatus($this->t('Innovations merged.'));
    $form_state->setRedirect('sti_dedupe.collections');
  }

}
