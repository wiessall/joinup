@api @group-j
Feature: Translating collections

  Background:
    Given the following users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   |           |
      | John Member |           |
      | Ross Auth   |           |
    And the following "collection" content:
      | title           | abstract                        | description                    |
      | Test collection | short description of collection | long description of collection |
    And the following collection user memberships:
      | collection      | user        | roles       |
      | Test collection | Lee Facil   | facilitator |
      | Test collection | John Member |             |

  Scenario Outline: Test translation access.
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "<title>" collection
    Then the response status code should be <code>

    Examples:
      | user        | title           | code |
      | Flora Mod   | Test collection | 200  |
      | Lee Facil   | Test collection | 200  |
      | John Member | Test collection | 403  |
      | Ross Auth   | Test collection | 403  |

  Scenario Outline: Test creating node translation from UI.
    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "Test collection" collection
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"

    # Create translation.
    When I go to the homepage of the "Test collection" collection
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test collection"
    Then the following fields should be present "Title, Abstract, Description"
    Then the following fields should not be present "Access URL, Closed, Contact information, Geographical coverage, Logo, Newsletter, Owner, Search keywords, Short ID, Site featured, State, Topic"
    When I fill in "Title" with "Testowa kolekcja"
    And I fill in "Abstract" with "Krótki opis kolekcji"
    And I fill in "Description" with "Długi opis kolekcji"
    And I press "Save (all translations)"
    Then I should see the success message "Collection Testowa kolekcja has been updated."
    And I should see the heading "Testowa kolekcja"
    And I should see the following lines of text:
      | Testowa kolekcja     |
      | Krótki opis kolekcji |
      | Długi opis kolekcji  |

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see the text "Testowa kolekcja"
    When I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowana kolekcja"
    And I press "Save (all translations)"
    Then I should see the success message "Collection Zaktualizowana kolekcja has been updated."
    And I should not see the heading "Testowa kolekcja"
    And I should see the heading "Zaktualizowana kolekcja"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see the text "Are you sure you want to delete the Polish translation of the content item Zaktualizowana kolekcja?"
    When I press "Delete Polish translation"
    Then I should see the success message "<delete message>"
    When I click "Translate" in the "Entity actions" region
    Then I should not see the text "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      | delete message                                                                 |
      | Flora Mod | Polish translation of the Collection Zaktualizowana kolekcja has been deleted. |
      | Lee Facil | The content item Zaktualizowana kolekcja Polish translation has been deleted.  |

  Scenario: Default language of new node should be same as language of the interface
    Given I am logged in as "Flora Mod"
    When I go to the propose collection form
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    When I switch to the "pl" language
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "pl"
