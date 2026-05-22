@api @group-d
Feature: Site sections
  As an analytics engineer
  I want visitor statistics to be grouped in sections
  So that I can better understand the needs of our users

  Background:
    Given the following "collection" content:
      | uri                                              | title                |
      | http://joinup.eu/collection/the-polygone-project | The Polygone Project |
    And the following "solution" content:
      | title            | collection           |
      | Miss Fashionista | The Polygone Project |
    And the following "discussion" content:
      | title         | body       | collection           |
      | Deep Carnival | Now online | The Polygone Project |
    And the following "event" content:
      | title                      | solution         |
      | Flat Beat 20th Anniversary | Miss Fashionista |

  Scenario: Reporting of site sections to the analytics platform
    # Check a couple of pages that do not belong to a particular collection or
    # solution. These should not report any site sections.
    Given I am on the homepage
    Then the analytics report should not include a site section
    Given I visit the collection overview page
    Then the analytics report should not include a site section
    Given I visit the contact form page
    Then the analytics report should not include a site section

    # Now do a sample check of a collection and solution overview page, as well
    # as some community content belonging to these groups.
    Given I visit the "The Polygone Project" collection
    Then the analytics report should include the site section "http://joinup.eu/collection/the-polygone-project"
    Given I visit the "Miss Fashionista" solution
    Then the analytics report should include the site section "http://joinup.eu/collection/the-polygone-project"
    Given I visit the "Deep Carnival" discussion
    Then the analytics report should include the site section "http://joinup.eu/collection/the-polygone-project"
    Given I visit the "Flat Beat 20th Anniversary" event
    Then the analytics report should include the site section "http://joinup.eu/collection/the-polygone-project"
