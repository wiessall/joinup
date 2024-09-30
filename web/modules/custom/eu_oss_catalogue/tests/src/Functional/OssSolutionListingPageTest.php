<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests the OSS solution listing page.
 *
 * @group eu_oss_catalogue
 */
class OssSolutionListingPageTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'views_bulk_operations',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->fetchSolutions('developers_italia', 5);
  }

  /**
   * Tests the public page (/oss-solutions).
   */
  public function testPublicOssSolutionPage(): void {
    $this->drupalLogin($this->drupalCreateUser());

    $assert_session = $this->assertSession();

    // Check the view exists, is access-restricted, and some defaults are there.
    $this->drupalGet('/oss-solutions');

    $assert_session->statusCodeEquals(200);
    $assert_session->titleEquals('Open Source Software Solutions | Drupal');
    $assert_session->fieldExists('Source');
    $assert_session->fieldExists('Category');
    $assert_session->fieldExists('License');
    $assert_session->buttonExists('Filter');
  }

  /**
   * Tests the administrative page.
   *
   * It tests the following page /admin/content/eu-oss-catalogue/oss-solutions.
   */
  public function testAdministrativeOssSolutionPage(): void {
    $this->drupalLogin($this->drupalCreateUser());

    $assert_session = $this->assertSession();

    $this->drupalGet('/admin/content/eu-oss-catalogue/oss-solutions');
    $assert_session->statusCodeEquals(403);

    $role = Role::load(RoleInterface::AUTHENTICATED_ID);
    $this->grantPermissions($role, [
      'administer eu oss catalogue',
    ]);
    $this->getSession()->reload();

    $this->drupalLogin($this->drupalCreateUser());

    $assert_session = $this->assertSession();

    // Check the view exists, is access-restricted, and some defaults are there.
    $this->drupalGet('/admin/content/eu-oss-catalogue/oss-solutions');

    $assert_session->statusCodeEquals(200);
    $assert_session->titleEquals('Open Source Software Solutions | Drupal');
    $assert_session->fieldExists('OSS Solution');
    $assert_session->fieldExists('Source Catalogue');
    $assert_session->fieldExists('Language');
    $assert_session->fieldExists('Published');
    $assert_session->fieldValueEquals('Archived', '0');
    $assert_session->buttonExists('Filter');

    $table = $assert_session->elementExists('css', '.views-element-container table.vbo-table tbody');

    $this->assertEquals(
      [
        [
          'GitHub solution 1',
          'GitHub solution 1',
          'https://github.com/dummy-namespace-1/dummy-name-1',
          '[EN] Solution 1: Short description',
          'Developers Italia',
          'EN, DE, IT',
          'Yes',
          'No',
          '',
        ],
        [
          'Project 1.1',
          'Project 1.1',
          'https://limited.self-hosted.gitlab.example.com/group1/project-1-1',
          'Group 1: Project 1: description',
          'Developers Italia',
          'EN, DE, IT',
          'Yes',
          'No',
          '',
        ],
        [
          'Project 1.2',
          'Project 1.2',
          'https://limited.self-hosted.gitlab.example.com/group1/project-1-2',
          '[EN] Group1: Project 2: Short description',
          'Developers Italia',
          'EN, DE, IT',
          'Yes',
          'No',
          '',
        ],
        [
          'Project 1.4',
          'Project 1.4',
          'https://limited.self-hosted.gitlab.example.com/group1/project-1-4',
          'Group 1: Project 4: description',
          'Developers Italia',
          'EN, DE',
          'Yes',
          'No',
          '',
        ],
        [
          'Project 1',
          'Project 1',
          'https://limited.self-hosted.gitlab.example.com/user1/project-1',
          'User 1: Project 1: description',
          'Developers Italia',
          'EN, DE',
          'Yes',
          'No',
          '',
        ],
      ],
      $this->getTableCellsText($table)
    );
  }

  /**
   * Returns table cell's text content grouped per table row.
   *
   * @return string[][]
   *   Text of the table cells in the table body, grouped per table row.
   */
  protected function getTableCellsText(NodeElement $table): array {
    $rows = $table->findAll('css', 'tbody tr');

    return array_reduce(
      $rows,
      function (array $carry, NodeElement $element): array {
        $cells = [];
        foreach ($element->findAll('css', 'td') as $cell) {
          assert($cell instanceof NodeElement);
          // Exclude date-like values. Example:
          if (preg_match('@^\d+/\d+/\d+ - \d+:\d+$@', $text = $cell->getText())) {
            continue;
          }
          $cells[] = $text;
        }
        $carry[] = $cells;
        return $carry;
      },
      []
    );
  }

}
