
@api @group-a
Feature: Translating contact information

  Background:
    And contact_information content:
      | name       |
      | Test contact information |

  Scenario: Users who do have edit access can manage translations.
    Given I am logged in as a user with the "moderator" role

    # Assert language select options/value.
    When I go to the edit form of the "Test contact information" contact_information
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"

    # Create translation.
    When I go to the homepage of the "Test contact information" contact_information
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test contact information"
    And the following fields should be present "Name, Website URL"
    Then the following fields should be present "Name"
    When I fill in "Name" with "Dane kontaktowe"
    And I fill in "Website URL" with "https://dame-kontaktowe.pl"
    Then the following fields should not be present "State"
    When I press "Update"
    Then I should see the success message "Contact information Dane kontaktowe has been updated."
    And I should see the heading "Dane kontaktowe"
    And I should see "https://dame-kontaktowe.pl"

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Dane kontaktowe"
    When I click "Edit" in the "Polish" row
    And I fill in "Name" with "Zaktualizowane dane"
    And I press "Update"
    Then I should see the success message "Contact information Zaktualizowane dane has been updated."
    And I should not see the heading "Dane kontaktowe"
    And I should see the heading "Zaktualizowane dane"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowane dane?"
    When I press "Delete Polish translation"
    Then I should see the success message "Polish translation of the Contact information Zaktualizowane dane has been deleted"
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

  Scenario: Users who do not have edit access cannot manage translations.
    When I am logged in as an authenticated
    When I visit the translation overview form of the "Test contact information" contact_information
    Then I should see the heading "Access denied"
