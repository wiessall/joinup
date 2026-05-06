@api @group-f
Feature: Edit licence through UI
  In order to manage licences
  As a moderator or a licence_manager
  I need to be able to edit licences through the UI.

  Scenario Outline: Moderators and Licence Managers must be able to view created licences and edit them.
    Given the following "licence" content:
      | title     | description            |
      | Licence 1 | Some dummy description |
    When I am logged in as a "<role>"
    And I am on the homepage
    When I click "Dashboard"
    When I click "Licences overview"
    # Check that proper access has been granted.
    Then I should see the heading "Licences"
    And I should see the text "Licence 1"

    # Check that the given role can edit the licence.
    When I click "Licence 1"
    Then I should see the link "Edit"
    When I click "Edit"
    And I fill in "Title" with "Licence 1.1"
    And I fill in "Description" with "This is some different description."
    And I select "Public domain" from "Type"
    And I press "Save"
    Then I should see the heading "Licence 1.1"

    Examples:
      | role            |
      | moderator       |
      | licence_manager |

  @javascript
  Scenario: Check if licence legal type displays all option groups.
    Given the following "licence" content:
      | title            | description                | uri                   | licence type  |
      | Creative commons | <p>This is so creative</p> | http://i-am-creative/ | Public domain |
    When I am logged in as a moderator
    And I go to the edit form of the "Creative commons" licence
    Then the "Licence legal type" tagify autocomplete should contain the following option groups:
      | Can        |
      | Must       |
      | Cannot     |
      | Compatible |
      | Law        |
      | Support    |
