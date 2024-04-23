<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Update helper for Joinup.
 */
class UpdateHelper implements ContainerInjectionInterface {

  /**
   * Constructs an UpdateHelper instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * Transforms the text field of a node type to paragraphs reference.
   *
   * Transformation needs quite lot amount of memory (peak usage is about 234 MB
   * at the end of updating document nodes). I tried to drop entity storage
   * caches of nodes and paragraphs, but it didn't help – and I didn't dig
   * deeper. Hence, this is a reusable helper to perform the updates.
   *
   * @param array $sandbox
   *   The sandbox for batching.
   * @param string $nodeType
   *   The node type.
   * @param string $sourceField
   *   Machine name of the source field (with "text", "string", or equivalent
   *   type).
   * @param string $destinationField
   *   The machine name of the destination entity reference revisions field,
   *   with "paragraph" target type.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function transformNodeBodyToLayoutParagraphs(
    array &$sandbox,
    string $nodeType,
    string $sourceField = 'body',
    string $destinationField = 'field_paragraphs_body'
  ): void {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    if (!isset($sandbox['nids'])) {
      $sandbox['nids'] = $nodeStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $nodeType)
        ->execute();
      $sandbox['max'] = count($sandbox['nids']);
    }

    $nids = array_splice($sandbox['nids'], 0, 100);
    foreach ($nodeStorage->loadMultiple($nids) as $node) {
      assert($node instanceof NodeInterface);
      if ($node->get($sourceField)->isEmpty()) {
        continue;
      }

      $layoutParagraph = Paragraph::create([
        'type' => 'layout',
      ]);
      $layoutParagraph->setBehaviorSettings(
        'layout_paragraphs',
        [
          'layout' => 'layout_onecol',
          'config' => ['label' => ''],
          'parent_uuid' => NULL,
          'region' => NULL,
        ]
      );
      $paragraph = Paragraph::create([
        'type' => 'simple_paragraph',
        'field_body' => $node->get($sourceField)->getValue(),
      ]);
      $paragraph->setBehaviorSettings(
        'layout_paragraphs',
        [
          'parent_uuid' => $layoutParagraph->uuid(),
          'region' => 'content',
        ]
      );

      // Skip notifications created and sent by joinup_notification module.
      $node->skip_notification = TRUE;
      $node->setNewRevision();
      $node
        ->set(
          $destinationField,
          [
            ['entity' => $layoutParagraph],
            ['entity' => $paragraph],
          ],
        )
        ->setRevisionCreationTime($this->time->getCurrentTime())
        ->setRevisionLogMessage('Automated update: Body converted to Layout Paragraphs')
        ->setSyncing(TRUE)
        ->save();
    }

    $sandbox['#finished'] = (int) empty($sandbox['nids']);
  }

}
