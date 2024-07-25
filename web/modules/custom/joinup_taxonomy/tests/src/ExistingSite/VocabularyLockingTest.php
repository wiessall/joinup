<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_taxonomy\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests adding, editing and deleting terms in an unlocked vocabulary.
 *
 * @group joinup_taxonomy
 */
class VocabularyLockingTest extends JoinupExistingSiteTestBase {

  use LoginTrait;

  /**
   * Tests adding, editing and deleting terms from an unlocked vocabulary.
   */
  public function testVocabularyLocking(): void {
    $session = $this->getSession();
    $assert = $this->assertSession();

    static::bypassReadOnlyConfig();
    $vocabulary = $this->createVocabulary();
    $vocabulary->setThirdPartySetting('joinup_taxonomy', 'locked', FALSE)->save();
    $account = $this->createUser([
      "create terms in {$vocabulary->id()}",
      "edit terms in {$vocabulary->id()}",
      "delete terms in {$vocabulary->id()}",
      'access taxonomy overview',
      // Bypass the legal notice form.
      'administer entity legal',
    ]);
    static::restoreReadOnlyConfig();

    $oneTimeLoginUrl = $this->user_pass_reset_url($account) . '/login';
    $session->visit($oneTimeLoginUrl);

    // Check that create route is accessible.
    $this->drupalGet("admin/structure/taxonomy/manage/{$vocabulary->id()}/add");
    $assert->statusCodeEquals(200);

    // Create a term.
    $term = $this->createTerm($vocabulary);

    // Check that the term can be edited.
    $this->drupalGet($term->toUrl('edit-form'));
    $assert->statusCodeEquals(200);

    // Check that the term can be deleted.
    $this->drupalGet($term->toUrl('delete-form'));
    $assert->statusCodeEquals(200);

    static::bypassReadOnlyConfig();
    $vocabulary->setThirdPartySetting('joinup_taxonomy', 'locked', TRUE)->save();
    static::restoreReadOnlyConfig();

    // Check that create route is not accessible.
    $this->drupalGet("admin/structure/taxonomy/manage/{$vocabulary->id()}/add");
    $assert->statusCodeEquals(403);

    // Check that the term cannot be edited.
    $this->drupalGet($term->toUrl('edit-form'));
    $assert->statusCodeEquals(403);

    // Check that the term can be deleted.
    $this->drupalGet($term->toUrl('delete-form'));
    $assert->statusCodeEquals(403);
  }

}
