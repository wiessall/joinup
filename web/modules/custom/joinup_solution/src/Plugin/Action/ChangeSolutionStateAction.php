<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\NodeInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowState;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action to archive solutions.
 *
 * @Action(
 *   id = "change_state:node:solution",
 *   label = @Translation("Change solution state action"),
 *   type = "node",
 *   confirm_form_route_name = "solution.change_state.multiple",
 *   requirements = {
 *     "_permission" = "manage solutions",
 *   },
 * )
 */
class ChangeSolutionStateAction extends ViewsBulkOperationsActionBase implements PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * Identifier of the temporary collection where we store our data.
   *
   * @const string
   */
  public const string TEMPSTORE_COLLECTION = 'solution.change_state.multiple';

  /**
   * The key in tempstore collection containing the solutions.
   *
   * @const string
   */
  public const string TEMPSTORE_SOLUTION_IDS_KEY = 'solutions';

  /**
   * The key in tempstore collection containing the target state.
   *
   * @const string
   */
  public const string TEMPSTORE_TARGET_STATE_KEY = 'targetState';

  /**
   * ID of the workflow to use in this action.
   *
   * @const string
   */
  public const string WORKFLOW_ID = 'solution_workflow';

  /**
   * Constructs a ChangeSolutionStateAction instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The temporary store factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user account proxy.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflowManager
   *   The (state machine) workflow manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected AccountProxyInterface $currentUser,
    protected WorkflowManagerInterface $workflowManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tempstore.private'),
      $container->get('current_user'),
      $container->get('plugin.manager.workflow'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['target_state' => NULL] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    // On the confirm form, we want to tell our users what we change and what we
    // don't change. To be able to do so, we need to return 'allowed' here.
    $access = AccessResult::allowed();
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $objects): void {
    $solutionIds = array_reduce(
      $objects,
      function (array $carry, NodeInterface $solution): array {
        if (!$solution instanceof SolutionInterface) {
          throw new \LogicException(sprintf(
            "The '%s' action can be applied only to '%s' entities, but the entity with ID '%s' is a '%s'.",
            $this->getPluginId(),
            SolutionInterface::class,
            $solution->id(),
            get_class($solution)
          ));
        }
        $carry[] = $solution->id();
        return $carry;
      },
      [],
    );

    $tempStore = $this->tempStoreFactory->get(self::TEMPSTORE_COLLECTION);
    $tempStore->set(self::TEMPSTORE_SOLUTION_IDS_KEY, $solutionIds);
    $tempStore->set(self::TEMPSTORE_TARGET_STATE_KEY, $this->getConfiguration()['target_state']);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?SolutionInterface $solution = NULL): void {
    $this->executeMultiple([$solution]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $workflow = $this->workflowManager->createInstance(self::WORKFLOW_ID);
    \assert($workflow instanceof WorkflowInterface);
    $workflowStates = $workflow->getStates();
    $workflowStateOptions = array_combine(
      array_keys($workflowStates),
      array_map(
        fn (WorkflowState $state) => $state->getLabel(),
        $workflowStates,
      )
    );
    $form['target_state'] = [
      '#title' => $this->t('Select the target workflow state to use in this action'),
      '#type' => 'select',
      '#options' => $workflowStateOptions,
      '#default_value' => $this->getConfiguration()['target_state'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    foreach (array_keys($this->defaultConfiguration()) as $key) {
      $this->configuration[$key] = $form_state->getValue($key);
    }
  }

}
