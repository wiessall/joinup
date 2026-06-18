@api @group-i
Feature: Topic terms are redirected to search page.

  Background:
    Given the following "topic" terms:
      | tid  | name      |
      | 5000 | Foo term  |
      | 5001 | Bar term  |
    And the following "collection" content:
      | title          | topic    |
      | Bar collection | Foo term |
    And the "Homepage topics" content listing contains:
      | title    |
      | Bar term |

  Scenario Outline: Topic terms not in the homepage queue are redirected to search page.
    Given I go to "<inbound url>"
    Then the relative url should be "<expected url>"
    And I should see the following facet summary "Foo term"

    Examples:
      | inbound url         | expected url                  |
      | /taxonomy/term/5000 | /search?f%5B0%5D=topic%3A5000 |

  Scenario: Topic terms present in the homepage queue are not redirected.
    Given I go to "/taxonomy/term/5001"
    Then the relative url should be "/taxonomy/term/5001"
