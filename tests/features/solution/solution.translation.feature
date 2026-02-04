@api @group-a
Feature: Translating solutions

  Scenario: Translate a solution
    Given solution content:
      | title           |
      | Test solution |
    Given solution content:
      | title         | abstract                      | description                  |
      | Test solution | short description of solution | long description of solution |

    # Create translation
    Given I am logged in as a user with the "moderator" role
    When I go to the homepage of the "Test solution" solution
    When I click "Translate" in the "Entity actions" region
    And I should see "Add" in the "Polish" row
    Then I click "Add" in the "Polish" row
    And I should see "Create Polish translation of Test solution"
    And I fill in "Title" with "Testowa solucja"
    And I fill in "Abstract" with "Krótki opis solucji"
    And I fill in "Description" with "Długi opis solucji"
    And the following fields should not be present "Access URL, Closed, Contact information, Geographical coverage, Logo, Newsletter, Owner, Search keywords, Short ID, Site featured, State, Topic"
    Then I press "Confirm"
    And I press "Publish"
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowa solucja"
    And I click "Testowa solucja"
    Then I should see "Testowa solucja"
    And I should see "Krótki opis solucji"
    And I should see "Długi opis solucji"
    And I should see "ISA²"
    And I should see "Contact name"
    And I should see "Only facilitators and authors can create content."
    And I should see "Moderated"

    # Edit translation
    When I go to the homepage of the "Test solution" solution
    And I click "Translate" in the "Entity actions" region
    Then I should see "Testowa solucja"
    And I should not see "Add" in the "Polish" row
    And I should see "Edit" in the "Polish" row
    Then I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowana solucja"
    Then I press "Confirm"
    Then I press "Publish"
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Testowa solucja"
    Then I should see "Zaktualizowana solucja"

    #Delete translation
    When I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete solution Zaktualizowana solucja?"
    And I should see the button "Delete Polish translation"
    When I press "Delete Polish translation"
    Then I should see "Polish translation of the Solution Zaktualizowana solucja has been deleted."
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Testowa solucja"
    And I should not see "Zaktualizowana solucja"
    And I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row
