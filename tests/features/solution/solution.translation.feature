@api @group-j
Feature: Translating solutions

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
    And solution content:
      | title         | collection      | abstract                      | description                  |
      | Test solution | Test collection | short description of solution | long description of solution |
    And the following solution user memberships:
      | solution      | user        | roles       |
      | Test solution | Lee Facil   | facilitator |
      | Test solution | John Member |             |

  Scenario Outline: Test translation access.
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "<title>" solution
    Then the response status code should be <code>

    Examples:
      | user        | title         | code |
      | Flora Mod   | Test solution | 200  |
      | Lee Facil   | Test solution | 200  |
      | John Member | Test solution | 403  |
      | Ross Auth   | Test solution | 403  |

  Scenario Outline: Test creating node translation from UI.
    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "Test solution" solution
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"

    # Create translation.
    When I go to the homepage of the "Test solution" solution
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test solution"
    Then the following fields should not be present "Access URL, Closed, Contact information, Geographical coverage, Logo, Newsletter, Owner, Search keywords, Short ID, Site featured, State, Topic"
    Then the following fields should be present "Title, Abstract, Description"
    When I fill in "Title" with "Testowa solucja"
    And I fill in "Abstract" with "Krótki opis solucji"
    And I fill in "Description" with "Długi opis solucji"
    When I press "Confirm"
    And I press "Save (all translations)"
    Then I should see the success message "Solution Testowa solucja has been updated."
    And I should see the heading "Testowa solucja"
    And I should see the following lines of text:
      | Testowa solucja     |
      | Krótki opis solucji |
      | Długi opis solucji  |

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowa solucja"
    When I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowana solucja"
    And I press "Confirm"
    And I press "Save (all translations)"
    Then I should see the success message "Solution Zaktualizowana solucja has been updated."
    And I should not see the heading "Testowa solucja"
    And I should see the heading "Zaktualizowana solucja"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowana solucja?"
    When I press "Delete Polish translation"
    Then I should see the success message "<delete message>"
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      | delete message                                                               |
      | Flora Mod | Polish translation of the Solution Zaktualizowana solucja has been deleted.  |
      | Lee Facil | The content item Zaktualizowana solucja Polish translation has been deleted. |
