@api
Feature: Asset distribution content archival.

  Scenario: Asset distribution content can be archived any time.
    Given solution:
      | title | Some AD solution |
      | state | published        |
    And licence content:
      | title  | description                               | deprecated |
      | WTFPLA | The WTFPLA is a rather permissive licence | no         |
    And distributions:
      | title        | licence | parent           | state     |
      | Hot Snake    | WTFPLA  | Some AD solution | published |
      | Red Snake    | WTFPLA  | Some AD solution | published |
      | Quality Yard | WTFPLA  | Some AD solution | archived  |

    When I am logged in as a moderator
    And I go to the edit form of the "Hot Snake" distribution
    Then I should see the button "Archive"

    When I press "Archive"
    Then I go to the "Hot Snake" distribution
    And I should see the text "Archive" in the "Page title" region
    When I go to the edit form of the "Hot Snake" distribution
    Then I should not see the button "Archive"
    And I should see the button "Unarchive"

    Given I go to the "Some AD solution" solution
    And I click "Add distribution"
    And I fill in the following:
      | Title       | Windows.zip      |
      | Description | Some description |
    And I upload the file "test.zip" to "Access URL"
    And I select "WTFPLA" from "Licence"
    And I press "Publish"
    And I go to the "Some AD solution" solution
    Then I should see the "Windows.zip" tile

    Given I am logged in as a facilitator of the "Some AD solution" solution
    And I go to the edit form of the "Red Snake" distribution
    Then I should not see the button "Archive"
    And I should not see the button "Unarchive"
    And I should not see the button "Delete"

    When I go to the edit form of the "Quality Yard" distribution
    Then I should not see the button "Archive"
    And I should not see the button "Unarchive"
    And I should not see the button "Delete"

    When I go to the "Some AD solution" solution
    And I click "Add distribution"
    And I fill in the following:
      | Title       | Linux.zip        |
      | Description | Some description |
    And I select "WTFPLA" from "Licence"

    And I upload the file "test.zip" to "Access URL"
    And I press "Publish"
    And I go to the "Some AD solution" solution
    Then I should see the "Linux.zip" tile
