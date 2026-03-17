@api @group-a
Feature: Translating owners

  Background:
    And owner content:
      | name       |
      | Test owner |

  Scenario: Users who do have edit access can manage translations.
    Given I am logged in as a user with the "moderator" role

    # Assert language select options/value.
    When I go to the edit form of the "Test owner" owner
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"

    # Create translation.
    When I go to the homepage of the "Test owner" owner
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test owner"
    Then the following fields should be present "Name"
    When I fill in "Name" with "Testowy właściciel"
    Then the following fields should not be present "State, Type"
    When I press "Update"
    Then I should see the success message "Owner Testowy właściciel has been updated."
    And I should see the heading "Testowy właściciel"

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowy właściciel"
    When I click "Edit" in the "Polish" row
    And I fill in "Name" with "Zaktualizowany właściciel"
    And I press "Update"
    Then I should see the success message "Owner Zaktualizowany właściciel has been updated."
    And I should not see the heading "Testowy właściciel"
    And I should see the heading "Zaktualizowany właściciel"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowany właściciel?"
    When I press "Delete Polish translation"
    Then I should see the success message "Polish translation of the Owner Zaktualizowany właściciel has been deleted"
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

  Scenario: Users who do not have edit access cannot manage translations.
    When I am logged in as an authenticated
    When I visit the translation overview form of the "Test owner" owner
    Then I should see the heading "Access denied"
