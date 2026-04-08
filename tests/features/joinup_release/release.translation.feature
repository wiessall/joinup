@api @group-j
Feature: Translating releases

  Background:
    Given users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   |           |
      | John Member |           |
      | Ross Auth   |           |
    And solution content:
      | title         |
      | Test solution |
    And release content:
      | title        | is version of |
      | Test release | Test solution |
    And the following solution user memberships:
      | solution      | user        | roles       |
      | Test solution | Lee Facil   | facilitator |
      | Test solution | John Member |             |

  Scenario Outline: Test translation access.
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "<title>" release
    Then the response status code should be <code>

    Examples:
      | user        | title        | code |
      | Flora Mod   | Test release | 200  |
      | Lee Facil   | Test release | 200  |
      | John Member | Test release | 403  |
      | Ross Auth   | Test release | 403  |

  Scenario Outline: Test creating node translation from UI.
    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "Test release" release
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"

    # Create translation.
    When I go to the homepage of the "Test release" release
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test release"
    Then the following fields should be present "Name, Release notes"
    Then the following fields should not be present "Documentation, Geographical coverage, Is version of, Language, Release version, Search keywords, Solution data, State, Status"
    When I fill in "Name" with "Testowe wydanie"
    And I fill in "Release notes" with "Notatki do Testowego wydania"
    When I press "Save (all translations)"
    Then I should see the success message "Release Testowe wydanie has been updated."
    And I should see the following lines of text:
      | Testowe wydanie              |
      | Notatki do Testowego wydania |

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowe wydanie"
    When I click "Edit" in the "Polish" row
    And I fill in "Name" with "Zaktualizowane wydanie"
    And I press "Save (all translations)"
    Then I should see the success message "Release Zaktualizowane wydanie has been updated."
    And I should not see "Testowe wydanie"
    And I should see "Zaktualizowane wydanie"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowane wydanie?"
    When I press "Delete Polish translation"
    Then I should see the success message "<delete message>"
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      | delete message                                                               |
      | Flora Mod | Polish translation of the Release Zaktualizowane wydanie has been deleted.   |
      | Lee Facil | The content item Zaktualizowane wydanie Polish translation has been deleted. |

  Scenario: Default language of new node should be same as language of the interface
    Given solution content:
      | title           |
      | Test solution 2 |
    Given I am logged in as "Flora Mod"
    When I go to the "Test solution 2" solution
    And I click "Add release" in the plus button menu
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    When I switch to the "pl" language
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "pl"
