@api @group-j
Feature: Translating distributions

  Background:
    Given users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   |           |
      | John Member |           |
      | Ross Auth   |           |
    Given solution content:
      | title         |
      | Test solution |
    And the following solution user memberships:
      | solution      | user        | roles       |
      | Test solution | Lee Facil   | facilitator |
      | Test solution | John Member |             |
    And distribution content:
      | title             | solution      |
      | Test distribution | Test solution |

  Scenario Outline: Test translation access.
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "<title>" distribution
    Then the response status code should be <code>

    Examples:
      | user        | title              | code |
      | Flora Mod   | Test distribution  | 200  |
      | Lee Facil   | Test distribution  | 200  |
      | John Member | Test distribution  | 403  |
      | Ross Auth   | Test distribution  | 403  |

  Scenario Outline: Test creating node translation from UI.
    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "Test distribution" distribution
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    # Create translation.
    When I go to the homepage of the "Test distribution" distribution
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then the following fields should be present "Title, Description"
    Then the following fields should not be present "Access URL, File size, Format, Groups audience, Licence, Release, Representation technique , State, Status"
    Then I should see the heading "Create Polish translation of Test distribution"
    When I fill in "Title" with "Testowa dystrybucja"
    And I fill in "Description" with "Opis dystrybucji"
    When I press "Update"
    Then I should see the success message "Distribution Testowa dystrybucja has been updated."
    And I should see the heading "Testowa dystrybucja"
    And I should see "Opis dystrybucji"

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowa dystrybucja"
    When I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowana dystrybucja"
    And I press "Update"
    Then I should see the success message "Distribution Zaktualizowana dystrybucja has been updated."
    And I should not see the heading "Testowa dystrybucja"
    And I should see the heading "Zaktualizowana dystrybucja"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowana dystrybucja?"
    When I press "Delete Polish translation"
    Then I should see the success message "Polish translation of the Distribution Zaktualizowana dystrybucja has been deleted."
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      |
      | Flora Mod |
      | Lee Facil |
