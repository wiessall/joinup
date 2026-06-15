@api @group-j
Feature: Translating documents

  Background:
    Given the following users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   |           |
      | John Member |           |
      | Ross Auth   |           |
    And the following "collection" content:
      | title           |
      | Test collection |
    And the following "solution" content:
      | title         | collection      |
      | Test solution | Test collection |
    And the following collection user memberships:
      | collection      | user        | roles       |
      | Test collection | Lee Facil   | facilitator |
      | Test collection | John Member |             |
    And the following solution user memberships:
      | solution      | user        | roles       |
      | Test solution | Lee Facil   | facilitator |
      | Test solution | John Member |             |
    And the following "document" content:
      | title                    | collection      | solution      | field_paragraphs_body         | resources |
      | Test collection document | Test collection |               | This is test document content | test.zip  |
      | Test solution document   |                 | Test solution | This is test document content | test.zip  |

  Scenario Outline: Test translation access.
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "<title>" document
    Then the response status code should be <code>

    Examples:
      | user        | title                    | code |
      | Flora Mod   | Test collection document | 200  |
      | Lee Facil   | Test collection document | 200  |
      | Flora Mod   | Test solution document   | 200  |
      | Lee Facil   | Test solution document   | 200  |
      | John Member | Test collection document | 403  |
      | Ross Auth   | Test collection document | 403  |
      | John Member | Test solution document   | 403  |
      | Ross Auth   | Test solution document   | 403  |

  Scenario Outline: Test creating node translation from UI.
    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "<title>" document
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    # Create translation.
    When I go to the homepage of the "<title>" document
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of <title>"
    Then the following fields should be present "Title, Abstract, Short title, Body, Documentation"
    Then the following fields should not be present "Geographical coverage, Groups audience, Licence, Logo, Search keywords, Site featured, State, Topic, Type of document"
    When I fill in "Title" with "Testowy dokument"
    And I fill in "Abstract" with "Opis dokumentu"
    And I fill in "Short title" with "Test. dok."
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value | To jest polskie tłumaczenie dokumentu |
    And I fill in "Description" with "Opis pliku dokumentu"
    When I press "Save (all translations)"
    Then I should see the success message "Document Testowy dokument has been updated."
    And I should see the following lines of text:
      | Test. dok.                            |
      | Testowy dokument                      |
      | To jest polskie tłumaczenie dokumentu |
      | Opis pliku dokumentu                  |

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see the text "Testowy dokument"
    When I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowany dokument"
    And I press "Save (all translations)"
    Then I should see the success message "Document Zaktualizowany dokument has been updated."
    And I should not see the heading "Testowy dokument"
    And I should see the heading "Zaktualizowany dokument"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see the text "Are you sure you want to delete the Polish translation of the content item Zaktualizowany dokument?"
    When I press "Delete Polish translation"
    Then I should see the success message "Polish translation of the Document Zaktualizowany dokument has been deleted."
    When I click "Translate" in the "Entity actions" region
    Then I should not see the text "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      | title                    |
      | Flora Mod | Test collection document |
      | Lee Facil | Test collection document |
      | Flora Mod | Test solution document   |
      | Lee Facil | Test solution document   |

  Scenario: Default language of new node should be same as language of the interface
    Given I am logged in as "Flora Mod"
    When I go to the "Test collection" collection
    And I click "Add document" in the plus button menu
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    When I switch to the "pl" language
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "pl"
