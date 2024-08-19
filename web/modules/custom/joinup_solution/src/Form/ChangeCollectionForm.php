<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Form;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to select the destination collection when moving solutions.
 */
class ChangeCollectionForm extends FormBase {

  /**
   * The private tempstore.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * The Joinup logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new form object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   The private tempstore factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selectionPluginManager
   *   The selection plugin manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory service.
   */
  public function __construct(
    PrivateTempStoreFactory $privateTempStoreFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected SelectionPluginManagerInterface $selectionPluginManager,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->tempStore = $privateTempStoreFactory->get('change_collection');
    $this->logger = $loggerFactory->get('joinup');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $source_collection = $this->getRouteMatch()->getParameter('node');

    $form['source_collection'] = [
      '#type' => 'value',
      '#value' => $source_collection,
    ];

    $form['solutions'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('The following solutions from %source collection will be moved to a new collection:', [
        '%source' => $source_collection->label(),
      ]),
      '#items' => array_map(function (SolutionInterface $solution): string {
        return $solution->label();
      }, $this->getSolutions()),
      '#empty' => $this->t('No solution was selected.'),
    ];

    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginBase $selection */
    $selection = $this->selectionPluginManager->createInstance('default:node', [
      'target_type' => 'node',
      'target_bundles' => ['collection'],
    ]);
    $selection_settings = $selection->getConfiguration() + [
      'match_operator' => 'CONTAINS',
    ];

    $form['destination_collection'] = [
      '#title' => $this->t('Select the destination collection'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_handler' => 'default:node',
      '#selection_settings' => $selection_settings,
      '#maxlength' => 2048,
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Move solutions'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('source_collection')->id() === $form_state->getValue('destination_collection')) {
      $form_state->setErrorByName('destination_collection', $this->t('The destination collection cannot be the same as the source collection.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $source_collection = $form_state->getValue('source_collection');
    $destination_collection_id = $form_state->getValue('destination_collection');

    if (!$destination_collection = $this->getGroupStorage()->load($destination_collection_id)) {
      throw new \RuntimeException("Cannot load collection entity with ID $destination_collection_id");
    }

    foreach ($this->getSolutions() as $solution) {
      $messenger_arguments = [
        '%solution' => $solution->toLink()->toString(),
        '%destination_collection' => $destination_collection->toLink()->toString(),
      ];
      $logger_arguments = [
        '@solution' => $solution->label(),
        '@destination_collection' => $destination_collection->label(),
        '@source_collection' => $source_collection->label(),
      ];

      try {
        // Prevent notification dispatching.
        // @see joinup_notification_dispatch_notification()
        $solution->skip_notification = TRUE;
        $solution->set('collection', $destination_collection_id)->save();
        $this->messenger()->addStatus($this->t('Solution %solution has been moved to %destination_collection.', $messenger_arguments));
        $this->logger->info("Solution '@solution' moved from '@source_collection' to '@destination_collection' collection.", $logger_arguments);
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t("Error while trying to change the collection for %solution solution.", $messenger_arguments));
        $this->logger->error("Error while trying to move solution '@solution' from '@source_collection' to '@destination_collection' collection.", $logger_arguments);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'change_collection';
  }

  /**
   * Returns a list of entities to be moved to the new collection.
   *
   * @return \Drupal\rdf_entity\RdfInterface[]
   *   A list of entities to be moved to the new collection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown when an entity with a non-existing storage is passed.
   */
  protected function getSolutions(): array {
    $solution_ids = $this->tempStore->get('solutions');
    return $solution_ids ? $this->getGroupStorage()->loadMultiple($solution_ids) : [];
  }

  /**
   * Returns the group storage.
   *
   * @return \Drupal\Core\Entity\ContentEntityStorageInterface
   *   The group entity storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown when an entity with a non-existing storage is passed.
   */
  protected function getGroupStorage(): ContentEntityStorageInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    assert($storage instanceof ContentEntityStorageInterface);
    return $storage;
  }

}
