<?php

declare(strict_types=1);

namespace Drupal\entityqueue_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block that shows the contents of an entityqueue.
 *
 * @Block(
 *   id = "entityqueue_block",
 *   admin_label = @Translation("Entityqueue block"),
 *   category = @Translation("Entityqueue"),
 *   deriver = "Drupal\entityqueue_block\Plugin\Derivative\EntityQueueBlock"
 * )
 */
class EntityQueueBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    readonly string $plugin_id,
    readonly array $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\entityqueue\EntitySubqueueInterface $subqueue */
    $subqueue = $this->entityTypeManager
      ->getStorage('entity_subqueue')
      ->load($this->getDerivativeId());

    /** @var \Drupal\entityqueue\EntitySubqueueItemsFieldItemList $item_list */
    $item_list = $subqueue->get('items');

    $build['entities'] = $this->entityTypeManager
      ->getViewBuilder($subqueue->getQueue()->getTargetEntityTypeId())
      ->viewMultiple($item_list->referencedEntities(), $this->configuration['view_mode']);

    // Provide a contextual link to edit the entity subqueue.
    $build['#contextual_links']['entityqueue'] = [
      'route_parameters' => [
        'entity_queue' => $subqueue->getQueue()->id(),
        'entity_subqueue' => $subqueue->id(),
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'view_mode' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\entityqueue\EntityQueueInterface $queue */
    $queue = $this->entityTypeManager->getStorage('entity_queue')->load($this->getDerivativeId());
    $target_type = $queue->getTargetEntityTypeId();

    $form = parent::buildConfigurationForm($form, $form_state);

    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#default_value' => $this->configuration['view_mode'],
      '#required' => TRUE,
    ];

    /** @var \Drupal\entityqueue\EntityQueueInterface[] $queues */
    $viewmodes = $this->entityDisplayRepository->getAllViewModes()[$target_type] ?? [];
    foreach ($viewmodes as $machine_name => $viewmode_info) {
      $form['view_mode']['#options'][$machine_name] = $viewmode_info['label'];
    }

    // Sort the dropdown options alphabetically to make them easier to navigate.
    asort($form['view_mode']['#options']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['view_mode'] = (string) $form_state->getValue('view_mode');
  }

}
