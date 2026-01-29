@api @group-a
Feature: Translating collections

  Scenario: Translate a collection
    Given collection content:
      | title           | abstract                        | description                    |
      | Test collection | short description of collection | long description of collection |
    Given I am logged in as a user with the "developer" role
    When I go to the edit form of the "Test collection" collection
    Then I press "Publish"
    When I visit "/collection/test-collection/translations"
    Then I should see a "a[hreflang='pl']" element
    And I check "edit-languages-pl"
    Then I click the "Add" link with hreflang "pl"
    And I should see "Create Polish translation of Test collection"
    And I fill in "Title" with "Testowa kolekcja"
    And I fill in "Abstract" with "Krótki opis kolekcji"
    And I fill in "Description" with "Długi opis kolekcji"
    Then I press "Publish"
    When I visit "/collection/test-collection/translations"
    Then I should see "Testowa kolekcja"
    And I click "Testowa kolekcja"
    Then I should see "Testowa kolekcja"
    And I should see "Krótki opis kolekcji"
    And I should see "Długi opis kolekcji"
