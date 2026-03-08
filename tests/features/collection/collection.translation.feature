@api @group-a
Feature: Translating collections

  Background:
    Given users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   |           |
      | John Member |           |
      | Ross Auth   |           |
    And collection content:
      | title           | abstract                        | description                    |
      | Test collection | short description of collection | long description of collection |
    And the following collection user memberships:
      | collection      | user        | roles       |
      | Test collection | Lee Facil   | facilitator |
      | Test collection | John Member |             |

  Scenario Outline: Users who do have edit access can manage translations.
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
    When I fill in "Title" with "Testowa kolekcja"
    And I fill in "Abstract" with "Krótki opis kolekcji"
    And I fill in "Description" with "Długi opis kolekcji"
    Then the following fields should not be present "Access URL, Closed, Contact information, Geographical coverage, Logo, Newsletter, Owner, Search keywords, Short ID, Site featured, State, Topic"
    When I press "Publish"
    Then I should see the success message "Collection Testowa kolekcja has been updated."
    And I should see the heading "Testowa kolekcja"
    And I should not see the following lines of text:
      | "Testowa kolekcja"     |
      | "Krótki opis kolekcji" |
      | "Długi opis kolekcji"  |

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowa kolekcja"
    When I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowana kolekcja"
    And I press "Publish"
    Then I should see the success message "Collection Zaktualizowana kolekcja has been updated."
    And I should not see the heading "Testowa kolekcja"
    And I should see the heading "Zaktualizowana kolekcja"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowana kolekcja?"
    When I press "Delete Polish translation"
    # @todo Inconsistent messages, see ISAICP-10959
    Then I should see the success message "<delete message>"
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      | delete message                                                                 |
      | Flora Mod | Polish translation of the Collection Zaktualizowana kolekcja has been deleted. |
      | Lee Facil | The content item Zaktualizowana kolekcja Polish translation has been deleted.  |

  Scenario Outline: Users who do not have edit access cannot manage translations.
    Given I am logged in as "<user>"
    When I go to the homepage of the "Test collection" collection
    Then I should not see the link "Edit" in the "Entity actions" region
    And I should not see the link "Translate" in the "Entity actions" region

    Examples:
      | user        |
      | John Member |
      | Ross Auth   |
