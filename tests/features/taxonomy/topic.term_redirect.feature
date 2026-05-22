@api @group-i
Feature: Topic terms are redirected to search page.

  Background:
    Given the following "topic" terms:
      | tid  | name     |
      | 5000 | Foo term |
    And the following "collection" content:
      | title          | topic    |
      | Bar collection | Foo term |

  Scenario Outline: Topic terms are redirected to search page.
    Given I go to "<inbound url>"
    Then the relative url should be "<expected url>"
    And I should see the following facet summary "Foo term"

    Examples:
      | inbound url         | expected url                  |
      | /taxonomy/term/5000 | /search?f%5B0%5D=topic%3A5000 |
