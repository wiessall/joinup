@api @group-i
Feature: Topic terms are redirected to search page.

  Scenario: Topic terms are redirected to search unless present in the homepage queue.
    Given the following "topic" terms:
      | name     |
      | Foo term |
      | Bar term |
    And the following "collection" content:
      | title          | topic    |
      | Bar collection | Foo term |
    And the following topics are enabled in the homepage:
      | Bar term |

    When I go to the "Foo term" topic term page
    Then I should see the heading "Advanced search"
    And I should see the following facet summary "Foo term"

    When I go to the homepage
    And I click "Bar term"
    Then I should not see the heading "Advanced search"
    And I should see the heading "Bar term"
