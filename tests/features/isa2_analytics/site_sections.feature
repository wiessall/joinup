@api @group-d
Feature: Site sections
  As an analytics engineer
  I want visitor statistics to be grouped in sections
  So that I can better understand the needs of our users

  Background:
    Given collection content:
      | uri                                              | title                | state     |
      | http://joinup.eu/collection/the-polygone-project | The Polygone Project | published |
    And solution content:
      | title            | collection           | state     |
      | Miss Fashionista | The Polygone Project | published |
    And discussion content:
      | title         | body       | state     | collection           |
      | Deep Carnival | Now online | published | The Polygone Project |
    And event content:
      | title                      | state     | solution         |
      | Flat Beat 20th Anniversary | published | Miss Fashionista |

  Scenario: Reporting of site sections to the analytics platform
    # Check a couple of pages that do not belong to a particular collection or
    # solution. These should not report any site sections.
    Given I am on the homepage
    Then the analytics report should not include a site section
    Given I visit the collection overview
    Then the analytics report should not include a site section
    Given I visit the contact form
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
