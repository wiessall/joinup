<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

/**
 * EU OSS Catalogue test.
 *
 * @group eu_oss_catalogue
 */
class DevelopersItaliaFetchTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected array $controlledVocabTerms = [
    'oss_licence' => ['AGPL-3.0-or-later', 'BSD-3-Clause', 'EUPL-1.2', 'MIT'],
    'oss_category' => [
      'data-analytics',
      'data-collection',
      'digital-citizenship',
      'nothing',
      'website-builder',
    ],
  ];

  /**
   * @covers
   */
  public function testDevelopersItalia(): void {
    $this->fetchSolutions('developers_italia', 4);

    $this->assertOssSolution('GitHub solution 1', 'en');
    $this->assertOssContractor('ACME Ltd');
    $this->assertOssContractor('Das Auto GmbH');
    $this->assertOssContact('Peter Gregory');

    $this->assertOssSolution('Project 1.1', 'it');
    $this->assertOssContact('Big Brother');

    $this->assertOssSolution('Project 1.2', 'de');
    $this->assertOssContact('John Doe');
    $this->assertOssContact('Jane Roe');

    $this->assertOssSolution('Project 1', 'de');

    $this->assertCount(
      1,
      $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:17:"GitHub solution 1";}'),
      'Creation log entry for GitHub solution 1'
    );
    $this->assertCount(
      1,
      $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.1";}'),
      'Creation log entry for Project 1.1'
    );
    $this->assertCount(
      1,
      $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.2";}'),
      'Creation log entry for Project 1.2'
    );
    $this->assertCount(
      1,
      $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:9:"Project 1";}'),
      'Creation log entry for Project 1'
    );

    // Re-run the process and update one solution from a new source-provider.
    $this->fetchSolutions('testing_provider', 4);

    $this->assertFalse($this->getNodeByTitle('GitHub solution 1'));
    $updatedSolution = $this->getNodeByTitle('GitHub solution 1 (updated)', TRUE);
    $this->assertSame('3.0.0', $updatedSolution->get('oss_software_version')->value);
    $this->assertSame('9yacbHn2CYe62lcCBgD47vfMiQZfb8rEmh4Rg3ceMsg', $updatedSolution->get('oss_source_hash')->value);
    // Assert that the provider has been added to the list of providers.
    $this->assertSame(2, $updatedSolution->get('oss_source')->count());
    $expected_providers = ['developers_italia', 'testing_provider'];
    $this->assertSame($expected_providers, array_map(fn (array $item): string => $item['plugin_id'], $updatedSolution->get('oss_source')->getValue()));

    $this->assertSame('Update, because data has been changed in "Testing provider" source/catalogue', $updatedSolution->getRevisionLogMessage(), 'Update revision message for GitHub solution 1');
  }

  /**
   * Tests that when a solution is removed upstream, is archived locally.
   */
  public function testArchiveSolution(): void {
    $this->fetchSolutions('removing_solution_provider', 4);

    $this->assertNotFalse($this->getNodeByTitle('GitHub solution 1'));
    $this->assertNotFalse($this->getNodeByTitle('Project 1.1'));

    // Provided no longer has "Project 1.1", so it should be archived.
    $this->fetchSolutions('removing_solution_provider', 0);

    $solutionArchived = $this->getNodeByTitle('Project 1.1', TRUE);
    $this->assertNotFalse($solutionArchived);
    $this->assertSame('1', $solutionArchived->get('oss_archived')->getString());
    $this->assertSame(0, $solutionArchived->get('oss_source')->count());
    $this->assertSame('Archive, because removed (not present anymore) from "Removing solution provider" source/catalogue', $solutionArchived->getRevisionLogMessage());
  }

  /**
   * Tests that when a solution is removed upstream, the source is updated.
   */
  public function testRemovedSolution(): void {
    $this->fetchSolutions('removing_solution_provider', 4);

    $this->assertNotFalse($this->getNodeByTitle('GitHub solution 1'));
    $this->assertNotFalse($this->getNodeByTitle('Project 1.1'));

    // Import the solutions from developers_italia plugin so that
    // the "Project 1.1" has 2 sources.
    $this->fetchSolutions('developers_italia', 4);

    $solution2 = $this->getNodeByTitle('Project 1.1', TRUE);
    $this->assertSame(2, $solution2->get('oss_source')->count());

    // Run the removal of the solution again. The solution should not be removed
    // but the source should be removed.
    $this->fetchSolutions('removing_solution_provider', 0);

    $removedSolution = $this->getNodeByTitle('Project 1.1', TRUE);
    $this->assertNotEmpty($removedSolution);
    $this->assertSame('0', $removedSolution->get('oss_archived')->getString());
    $this->assertSame(1, $removedSolution->get('oss_source')->count());
    $this->assertSame('Update, because of the only removal "Removing solution provider" source/catalogue', $removedSolution->getRevisionLogMessage());
  }

  /**
   * Tests that text for short and long descriptions are parsed properly.
   */
  public function testMarkDownResolver(): void {
    $entity_view_builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
    $renderer = $this->container->get('renderer');
    $this->fetchSolutions('developers_italia', 4);

    // The fact that the solution is found means that the title was cleaned of
    // extra spaces.
    $solution = $this->getNodeByTitle('GitHub solution 1');

    // Assert that markdown is converted for short description.
    $field_items = $solution->get('oss__description');
    $render_array = $entity_view_builder->viewField($field_items);
    $rendered = (string) $renderer->renderPlain($render_array);
    $this->assertStringContainsString('[EN] Solution 1: <strong>Short description</strong>', $rendered);

    // Assert that markdown is converted for long description.
    $field_items = $solution->get('oss__long_description');
    $render_array = $entity_view_builder->viewField($field_items);
    $rendered = (string) $renderer->renderPlain($render_array);
    $this->assertStringContainsString('<strong>Some bold text</strong>', $rendered);
    $this->assertStringContainsString("<ul>\n<li>Some list item</li>\n<li>Another list item</li>\n</ul>", $rendered);
    $this->assertStringContainsString('<em>Some italic text</em>', $rendered);
  }

}
