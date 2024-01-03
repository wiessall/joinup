<?php

declare(strict_types = 1);

namespace Drupal\solution\Form;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\solution\Entity\SolutionInterface;
use Drupal\solution\Plugin\Action\ChangeSolutionStateAction;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirm form for ChangeSolutionStateAction.
 */
class ChangeSolutionStateForm extends ConfirmFormBase {

  /**
   * Constructs a new ChangeSolutionStateForm instance.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The temp store factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflowManager
   *   The workflow state manager.
   * @param \Drupal\Core\DestructableInterface|null $postRequestIndexer
   *   Search API's post request indexer service, if it is available.
   */
  public function __construct(
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected WorkflowManagerInterface $workflowManager,
    protected ?DestructableInterface $postRequestIndexer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.workflow'),
      $container->get('search_api.post_request_indexing', ContainerInterface::NULL_ON_INVALID_REFERENCE)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'solution.change_state.multiple';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('No items can be changed.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('<none>');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Confirm');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('None of the selected items can be changedTry to re-submit the form.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['description']['#type'] = 'item';
    [
      'solutions' => $solutions,
      'target_state' => $targetState,
    ] = $this->tempStoreFactory
      ->get(ChangeSolutionStateAction::TEMPSTORE_COLLECTION)
      ->get($this->currentUser()->id());

    $transitionables = [];
    $transitionNotApplicable = [];
    foreach ($solutions as $delta => $solution) {
      if (!$solution instanceof SolutionInterface) {
        continue;
      }
      if (
        $solution->getWorkflowState() !== $targetState &&
        $solution->isTargetWorkflowStateAllowed($targetState)
      ) {
        $transitionables[] = $solution->label();
        continue;
      }
      $transitionNotApplicable[] = $solution->label();
      unset($solutions[$delta]);
    }

    $form['applicable'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('The action will be applied on the following items:'),
      '#items' => $transitionables,
    ];
    $form['not_applicable'] = [
      '#theme' => 'item_list',
      '#title' => $this->t("The following items can't be changed:"),
      '#items' => $transitionNotApplicable,
    ];

    $form_state->setTemporaryValue('solutions', $solutions);
    if (empty($solutions)) {
      $form['actions']['submit']['#access'] = FALSE;
      $form['actions']['cancel']['#title'] = $this->t('Return to the previous form');
      unset($form['not_applicable']);
    }
    else {
      $form['#title'] = $this->t(
        'Confirmation of Changing the State to @state',
        ['@state' => $this->getTargetStateLabel($targetState)],
      );
      $form['description']['#markup'] = $this->formatPlural(
        count($transitionables),
        'Please confirm the state transition of the selected solution.',
        'Please confirm the state transition of the selected solutions.',
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $temporaryStore = $this->tempStoreFactory->get(ChangeSolutionStateAction::TEMPSTORE_COLLECTION);
    ['target_state' => $targetState] = $temporaryStore->get($uid = $this->currentUser()->id());
    // Clean temp store.
    $temporaryStore->delete($uid);
    if ($form_state->getValue('confirm')) {
      $labels = [];
      foreach ($form_state->getTemporaryValue('solutions') as $solution) {
        \assert($solution instanceof SolutionInterface);
        $solution->setWorkflowState($targetState)->save();
        $labels[] = $solution->label();
      }

      natsort($labels);

      $this->messenger()->addStatus(
        $this->formatPlural(
          count($labels),
          "The %solution-label solution's state was changed to @state.",
          "The following solutions' state was changed to @state: @solution-labels.",
          [
            '@state' => $solution->getWorkflowStateInstance()->getLabel(),
            '%solution-label' => $labelString = implode(', ', $labels),
            '@solution-labels' => $labelString,
          ]
        )
      );

      // We can assume post request indexer queue (in this PHP request) was
      // empty initially - we don't have to worry about that we index
      // thousands of items.
      $this->postRequestIndexer?->destruct();
    }
  }

  /**
   * Returns the label of the target state.
   *
   * @param string $stateId
   *   The ID of the target state.
   *
   * @return string
   *   The label of the target state.
   */
  protected function getTargetStateLabel(string $stateId): string {
    $workflow = $this->workflowManager->createInstance(ChangeSolutionStateAction::WORKFLOW_ID);
    \assert($workflow instanceof WorkflowInterface);
    return $workflow->getState($stateId)->getLabel();
  }

}
