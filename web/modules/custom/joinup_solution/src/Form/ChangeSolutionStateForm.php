<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Form;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\joinup_solution\Plugin\Action\ChangeSolutionStateAction;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirm form for ChangeSolutionStateAction.
 *
 * @see \Drupal\joinup_solution\Plugin\Action\ChangeSolutionStateAction
 */
class ChangeSolutionStateForm extends ConfirmFormBase {

  /**
   * The private temporary data store.
   */
  protected ?PrivateTempStore $privateTempStore;

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
  public static function create(ContainerInterface $container): self {
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
    return $this->t(
      'Confirmation of Changing the State of Solutions to @state',
      ['@state' => $this->getTargetStateLabel()],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    $collection = $this->getRouteMatch()->getParameter('node');
    return Url::fromRoute('view.manage_collection_solutions.manage_solutions', [
      'node' => $collection->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->formatPlural(
      count($this->getFilteredSolutionList(TRUE)),
      'Please confirm the state transition of the selected solution.',
      'Please confirm the state transition of the selected solutions.',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): RedirectResponse|array {
    $form = parent::buildForm($form, $form_state);
    $form['description']['#type'] = 'item';
    $form['applicable'] = $this->buildTransitionableItemList();
    $form['not_applicable'] = $this->buildNonTransitionableItemList();

    $noMatchingItems = empty($form['applicable']['#items']);
    if ($noMatchingItems && $this->getTargetStateId()) {
      $this->messenger()->addWarning(
        $this->t('None of the selected items can be changed to state %state.', [
          '%state' => $this->getTargetStateLabel(),
        ])
      );
    }

    return $noMatchingItems
      ? $this->redirectToForm()
      : $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $transitionalbleSolutions = $this->getFilteredSolutionList(TRUE);
    if ($form_state->getValue('confirm') && $transitionalbleSolutions) {
      $targetState = $this->getTargetStateId();
      $labels = [];
      foreach ($transitionalbleSolutions as $solution) {
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
            '@state' => $this->getTargetStateLabel(),
            '%solution-label' => $labelString = implode(', ', $labels),
            '@solution-labels' => $labelString,
          ]
        )
      );

      // In Joinup, Search API indexes are updated "immediately". Actually, this
      // means that when an indexed entity is changed (or an entity is added) in
      // a PHP thread, re-indexing is triggered by
      // \Drupal\search_api\Utility\PostRequestIndexing after the response is
      // sent to the client. This means that there will be two concurrent PHP
      // threads:
      // - The initial thread, which is performing the re-indexing of the
      //   changed entities.
      // - The second thread, which starts to render the (VBO) form what the
      //   user did use to change the state of the solutions.
      //
      // In 99% of the cases, indexing is lot slower than rendering. So when the
      // second thread renders the rows of the (Search API index based) VBO
      // form, it will still use the old state of the index. This means that the
      // user will get the rendered Views rows which are based on the outdated
      // state of the entities; and even if we apply the patch from
      // https://drupal.org/i/3327645, the user won't be able to see the
      // up-to-date data until they reload the page.
      //
      // But luckily, the issue above is quite easy to be solved here:
      // We can assume post request indexer queue (in this PHP request) was
      // empty initially - we don't have to worry about that we index
      // thousands of items.
      $this->postRequestIndexer?->destruct();
    }

    $this->deleteTempStore();
  }

  /**
   * Returns the label of the target state.
   *
   * @return string|null
   *   The label of the target state, or NULL if the data store is empty.
   */
  protected function getTargetStateLabel(): ?string {
    $workflow = $this->workflowManager->createInstance(ChangeSolutionStateAction::WORKFLOW_ID);
    \assert($workflow instanceof WorkflowInterface);
    $workflowId = $this->getTargetStateId();
    $workflowState = $workflowId
      ? $workflow->getState($workflowId)
      : NULL;
    return $workflowState?->getLabel();
  }

  /**
   * Returns the action's data collection stored in private data store.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   *   The state change action's data collection stored in private data store.
   */
  protected function temporaryStore(): PrivateTempStore {
    if (!isset($this->privateTempStore)) {
      return $this->privateTempStore = $this->tempStoreFactory->get(ChangeSolutionStateAction::TEMPSTORE_COLLECTION);
    }
    return $this->privateTempStore;
  }

  /**
   * Returns the target workflow state's ID from private temporary store.
   *
   * @return string|null
   *   The target workflow state's ID from private temporary store, or NULL
   *   if the data store is empty.
   */
  protected function getTargetStateId(): ?string {
    return $this->temporaryStore()->get(ChangeSolutionStateAction::TEMPSTORE_TARGET_STATE_KEY);
  }

  /**
   * Builds the item list of the solutions which cannot switch to target state.
   *
   * @return array
   *   An item list renderable array with the solution labels which cannot
   *   switch to the target state.
   */
  protected function buildNonTransitionableItemList(): array {
    $solutionLabels = array_reduce(
      $this->getFilteredSolutionList(FALSE),
      function (array $carry, SolutionInterface $solution): array {
        $carry[] = $solution->label();
        return $carry;
      },
      []
    );
    return [
      '#theme' => 'item_list',
      '#title' => $this->t("The following items can't be changed:"),
      '#items' => $solutionLabels,
    ];
  }

  /**
   * Builds the item list of the solutions which can change to target state.
   *
   * @return array
   *   An item list renderable array with the solution labels which can be
   *   changed to the target state.
   */
  protected function buildTransitionableItemList(): array {
    $solutionLabels = array_reduce(
      $this->getFilteredSolutionList(TRUE),
      function (array $carry, SolutionInterface $solution): array {
        $carry[] = $solution->label();
        return $carry;
      },
      []
    );
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('The action will be applied on the following items:'),
      '#items' => $solutionLabels,
    ];
  }

  /**
   * Returns filtered list of solutions from temporary store.
   *
   * @param bool $canSwitchToTargetState
   *   If called with TRUE, then the method returns solutions which can be
   *   changed to the target state by the current user. If this parameter is
   *   FALSE, then returns those solutions which cannot switch to the target
   *   state.
   *
   * @return \Drupal\joinup_solution\Entity\SolutionInterface[]
   *   Filtered list of solutions from temporary store.
   */
  protected function getFilteredSolutionList(bool $canSwitchToTargetState): array {
    if (empty($targetState = $this->getTargetStateId())) {
      return [];
    }

    return array_filter(
      $this->getSolutions(),
      function (SolutionInterface $solution) use ($targetState, $canSwitchToTargetState): bool {
        $canTransition = $solution->getWorkflowState() !== $targetState &&
          $solution->isTargetWorkflowStateAllowed($targetState);
        return $canSwitchToTargetState
          ? $canTransition
          : !$canTransition;
      }
    );
  }

  /**
   * Deletes the temporary data of the solution state change action.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   When no lock can be acquired for the backend storage.
   */
  protected function deleteTempStore(): void {
    $this->temporaryStore()->delete(ChangeSolutionStateAction::TEMPSTORE_TARGET_STATE_KEY);
    $this->temporaryStore()->delete(ChangeSolutionStateAction::TEMPSTORE_SOLUTION_IDS_KEY);
  }

  /**
   * Returns the solutions which ID is stored in temporary store.
   *
   * @return \Drupal\joinup_solution\Entity\SolutionInterface[]
   *   List of solutions from temporary store.
   */
  protected function getSolutions(): array {
    $ids = $this->temporaryStore()->get(ChangeSolutionStateAction::TEMPSTORE_SOLUTION_IDS_KEY) ?: [];
    return $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
  }

  /**
   * Returns a redirect to the collection's manage solutions path.
   *
   * Also deletes the temporary store.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response to the collection's manage solutions path.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   When the cancel URL cannot be built (because the current route does not
   *   have a CollectionInterface object parameter).
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   When no lock can be acquired for the temporary data store's backend
   *   storage to clear it.
   */
  protected function redirectToForm(): RedirectResponse {
    $this->deleteTempStore();
    try {
      return new RedirectResponse($this->getCancelUrl()->toString());
    }
    catch (\Exception) {
    }
    throw new NotFoundHttpException();
  }

}
