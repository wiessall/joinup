<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Url;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Deployment and smoke test helper for Interoperable Europe Act document.
 */
class DeployAndSmokeTestHelper {

  public const IEA_DOCUMENT_UUID = '07508859-8dc4-4e06-8113-2369f8d8fda1';
  public const IEA_DOCUMENT_PARAGRAPH_UUID = '71ffe1d3-14f6-47ce-98df-ccbdcaa35bc8';
  public const IEA_DOCUMENT_RAW_CONTENT_FILE = __DIR__ . '/../joinup_core.deploy.iap.document.html';

  /**
   * Creates the Interoperable Europe Act document custom page node.
   *
   * @return string
   *   Message of the deployment hook.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function createIeaCustomPage(): string {
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    assert($nodeStorage instanceof NodeStorageInterface);
    $paragraphsStorage = \Drupal::entityTypeManager()->getStorage('paragraph');
    assert($paragraphsStorage instanceof ContentEntityStorageInterface);

    $layoutParagraph = $paragraphsStorage->create([
      'type' => 'layout',
    ]);
    assert($layoutParagraph instanceof ParagraphInterface);
    $layoutParagraph->setBehaviorSettings(
      'layout_paragraphs',
      [
        'layout' => 'layout_onecol',
        'config' => ['label' => ''],
        'parent_uuid' => NULL,
        'region' => NULL,
      ]
    );
    $paragraph = $paragraphsStorage->create([
      'type' => 'simple_paragraph',
      'uuid' => self::IEA_DOCUMENT_PARAGRAPH_UUID,
      'field_body' => [
        [
          'value' => file_get_contents(self::IEA_DOCUMENT_RAW_CONTENT_FILE),
          'format' => 'content_editor',
        ],
      ],
    ]);
    assert($paragraph instanceof ParagraphInterface);
    $paragraph->setBehaviorSettings('layout_paragraphs', [
      'parent_uuid' => $layoutParagraph->uuid(),
      'region' => 'content',
    ]);

    $ieaDocument = $nodeStorage->create([
      'uuid' => self::IEA_DOCUMENT_UUID,
      'title' => 'Regulation (EU) 2024/903 of the European Parliament and of the Council of 13 March 2024 laying down measures for a high level of public sector interoperability across the Union (Interoperable Europe Act)',
      'type' => 'custom_page',
      'field_hide_title' => TRUE,
      'status' => 0,
      'field_paragraphs_body' => [
        ['entity' => $layoutParagraph],
        ['entity' => $paragraph],
      ],
    ]);
    assert($ieaDocument instanceof CustomPageInterface);
    $ieaDocument->setWorkflowState('draft');
    $ieaDocument->skip_notification = TRUE;
    $ieaDocument->save();

    $front = Url::fromRoute(
      route_name: '<front>',
      options: ['absolute' => TRUE]
    );
    return sprintf(
      "Created the node 'Interoperable Europe Act' as unpublished custom page with UUID '%s'. It can be accessed by users having at least the moderator role at '%s'.",
      $ieaDocument->uuid(),
      $front->toString() . $ieaDocument->toUrl()->getInternalPath(),
    );
  }

}
