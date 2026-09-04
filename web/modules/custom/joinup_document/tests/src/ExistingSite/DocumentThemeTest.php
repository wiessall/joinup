<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_document\ExistingSite;

use Drupal\joinup_search\SearchIndexCommitter;
use Drupal\Tests\joinup_core\Traits\TestFileTrait;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;

/**
 * Various tests related to document theming.
 */
class DocumentThemeTest extends JoinupExistingSiteTestBase {

  use TestFileTrait;

  /**
   * Tests that the logo affects relative fields in the listing tile.
   */
  public function testDocumentLogoListing(): void {
    $collection = $this->createNode([
      'type' => 'collection',
      'title' => $this->randomString(),
      'field_state' => 'published',
    ]);

    $document = $this->createNode([
      'type' => 'document',
      'title' => 'Document title',
      'og_audience' => $collection->id(),
      'body' => 'Hello document body',
      'field_short_title' => 'DocTitle',
      'field_state' => 'published',
    ]);

    $search_index_committer = \Drupal::service(SearchIndexCommitter::class);
    $assert = $this->assertSession();

    $search_index_committer->commitPendingChanges();
    $this->drupalGet($collection->toUrl());
    $assert->pageTextContains('Document title');
    $assert->pageTextNotContains('Hello document body');

    $file = $this->getTestFile('text');
    $file->save();
    $document->set('field_document_logo', $file)->save();

    $search_index_committer->commitPendingChanges();
    $this->getSession()->reload();

    $assert->pageTextContains('Document title');
    $assert->pageTextNotContains('Hello document body');
  }

}
