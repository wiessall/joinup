@api @group-a
Feature: Translating collections

  Scenario: Translate a collection
    Given collection content:
      | title           | abstract                        | description                    |
      | Test collection | short description of collection | long description of collection |

    # Create translation
    Given I am logged in as a user with the "moderator" role
    When I go to the homepage of the "Test collection" collection
    When I click "Translate" in the "Entity actions" region
    And I should see "Add" in the "Polish" row
    Then I click "Add" in the "Polish" row
    And I should see "Create Polish translation of Test collection"
    And I fill in "Title" with "Testowa kolekcja"
    And I fill in "Abstract" with "Krótki opis kolekcji"
    And I fill in "Description" with "Długi opis kolekcji"
    And the following fields should not be present "Access URL, Closed, Contact information, Geographical coverage, Logo, Newsletter, Owner, Search keywords, Short ID, Site featured, State, Topic"
    Then I press "Publish"
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowa kolekcja"
    And I click "Testowa kolekcja"
    Then I should see "Testowa kolekcja"
    And I should see "Krótki opis kolekcji"
    And I should see "Długi opis kolekcji"
    And I should see "ISA²"
    And I should see "Contact name"
    And I should see "Open collection"
    And I should see "Moderated"

    # Edit translation
    When I go to the homepage of the "Test collection" collection
    And I click "Translate" in the "Entity actions" region
    Then I should see "Testowa kolekcja"
    And I should not see "Add" in the "Polish" row
    And I should see "Edit" in the "Polish" row
    Then I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowana kolekcja"
    Then I press "Publish"
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Testowa kolekcja"
    Then I should see "Zaktualizowana kolekcja"

    #Delete translation
    When I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete collection Zaktualizowana kolekcja?"
    And I should see the button "Delete Polish translation"
    When I press "Delete Polish translation"
    Then I should see "Polish translation of the Collection Zaktualizowana kolekcja has been deleted."
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Testowa kolekcja"
    And I should not see "Zaktualizowana kolekcja"
    And I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row
