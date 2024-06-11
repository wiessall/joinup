<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types=1);

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Url;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Delete stale page.
 */
function joinup_core_deploy_110800(): void {
  \Drupal::entityTypeManager()->getStorage('node')->load(703013)->delete();
}

/**
 * Create Interoperable Europe Act document as custom page.
 */
function joinup_core_deploy_110801(): string {
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
    'field_body' => [
      [
        'value' => file_get_contents(__DIR__ . '/joinup_core.deploy.iap.document.html'),
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
    'uuid' => '07508859-8dc4-4e06-8113-2369f8d8fda1',
    'title' => 'Regulation (EU) 2024/903 of the European Parliament and of the Council of 13 March 2024 laying down measures for a high level of public sector interoperability across the Union (Interoperable Europe Act)',
    'type' => 'custom_page',
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
    "Created the node 'Interoperable Europe Act' as unpublished custom page. It can be accessed by users having at least the moderator role at '%s'.",
    $front->toString() . $ieaDocument->toUrl()->getInternalPath(),
  );
}
