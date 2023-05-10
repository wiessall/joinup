@api @group-clone
Feature: Test post update functionality for the ISAICP-7450 EPIC.

  Scenario Outline: New groups archive switch is on the top region by default.
    Given <group>:
      | title | Some <group> |
      | state | validated    |
    And news content:
      | title                  | <group>      | state     |
      | Some non archived news | Some <group> | validated |
      | Some archived news     | Some <group> | archived  |

    When I go to the "Some <group>" <group>
    Then I should not see the "<group> content archived toggle" facet
    When the "Some <group>" <group> is configured to show "All" community content in the overview
    And I go to the "Some <group>" <group>
    Then I should see the "<group> content archived toggle" facet

    Examples:
      | group      |
      | collection |
      | solution   |
