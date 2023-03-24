<?php

declare(strict_types = 1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\joinup_group_content_management\Exception\ActionException;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a VBO action that unarchive content.
 *
 * @Action(
 *   id = "joinup_unarchive",
 *   label = @Translation("Unarchive content"),
 *   type = "node"
 * )
 */
class UnarchiveAction extends StateActionBase {

  /**
   * Constructs object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected RendererInterface $renderer, protected EntityTypeManagerInterface $entityTypeManager, protected WorkflowHelperInterface $workflowHelper) {
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
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('joinup_workflow.workflow_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function canBeExecuted(NodeInterface $node): void {
    if (!$node instanceof EntityWorkflowStateInterface) {
      throw new ActionException('This content does not support workflow.');
    }

    $actual = $node->getWorkflowState();
    if ($actual != self::ARCHIVE_STATE) {
      throw new ActionException('Entity must be ' . self::ARCHIVE_STATE);
    }

    $previous = $this->getPreviousRevision($node);
    if (!$previous) {
      throw new ActionException('Previous revision has not been found');
    }
  }

  /**
   * Gets previous revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface|null
   *   Revisionable entity.
   */
  protected function getPreviousRevision(NodeInterface $node): ?RevisionableInterface {
    $previousRevisionId = $this->getPreviousRevisionId($node);
    if (!$previousRevisionId) {
      return NULL;
    }

    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($node->getEntityTypeId());

    return $storage->loadRevision($previousRevisionId);
  }

  /**
   * Get previous revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity.
   *
   * @return int|null
   *   Previous revision.
   */
  protected function getPreviousRevisionId(NodeInterface $node): ?int {
    $entity_type = $node->getEntityTypeId();

    if (!$storage = $this->entityTypeManager->getStorage($entity_type)) {
      return NULL;
    }

    $revision_ids = $storage->getQuery()
      ->allRevisions()
      ->condition($this->entityTypeManager->getDefinition($entity_type)
        ->getKey('id'), $node->id())
      ->sort($this->entityTypeManager->getDefinition($entity_type)
        ->getKey('revision'), 'DESC')
      ->range(1, 1)
      ->execute();
    if ($revision_ids) {
      return array_keys($revision_ids)[0];
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function execute(?NodeInterface $node = NULL): void {
    if (!$node instanceof EntityWorkflowStateInterface) {
      return;
    }

    $this->workflowHelper->unarchiveEntity($node);
  }

}
