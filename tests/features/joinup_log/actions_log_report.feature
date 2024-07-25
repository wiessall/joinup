@api @group-a
Feature: Actions log
  In order to get a view on actions to our content
  As a facilitator
  I want to have access the actions log report

  Background:
    Given collections:
      | title         | state     |
      | Tilt and Turn | published |
    And solutions:
      | title    | state     | collection    |
      | Skylight | published | Tilt and Turn |

  Scenario: Access to report is limited.
    Given I am not logged in
    When I go to the actions log report for "Tilt and Turn"
    Then I should see the text "Sign in to continue"
    When I go to the actions log report for "Skylight"
    Then I should see the text "Sign in to continue"

    Given I am logged in as a moderator
    When I go to the actions log report for "Tilt and Turn"
    Then I should see the heading "Actions log"
    When I go to the actions log report for "Skylight"
    Then I should see the heading "Actions log"

    Given I am logged in as a facilitator of the "Tilt and Turn" collection
    When I go to the actions log report for "Tilt and Turn"
    Then I should see the heading "Actions log"

    Given I am logged in as a facilitator of the "Skylight" solution
    When I go to the actions log report for "Skylight"
    Then I should see the heading "Actions log"

    Given I am logged in as a member of the "Tilt and Turn" collection
    When I go to the actions log report for "Tilt and Turn"
    Then I should see the text "Access denied"

    Given I am logged in as a member of the "Skylight" solution
    When I go to the actions log report for "Skylight"
    Then I should see the text "Access denied"
