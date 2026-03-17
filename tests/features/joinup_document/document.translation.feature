@api @group-a
Feature: Translating documents

  Background:
    Given collection content:
      | title           |
      | Test collection |
    Given solution content:
      | title         |
      | Test solution |

  Scenario Outline: Users who do have edit access can manage translations.
    Given users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   |           |
      | John Member |           |
    Given the following <bundle> user memberships:
      | <bundle>      | user        | roles       |
      | Test <bundle> | Lee Facil   | facilitator |
      | Test <bundle> | John Member |             |
    And document content:
      | title         | <bundle>      | field_paragraphs_body         | resources |
      | Test document | Test <bundle> | This is test document content | test.zip  |

    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "Test document" document
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    # Create translation.
    When I go to the homepage of the "Test document" document
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test document"
    Then the following fields should be present "Title, Abstract, Short title, Body, Documentation"
    When I fill in "Title" with "Testowy dokument"
    And I fill in "Abstract" with "Opis dokumentu"
    And I fill in "Short title" with "Test. dok."
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value | To jest polskie tłumaczenie dokumentu |
    And I fill in "Description" with "Opis pliku dokumentu"
    Then the following fields should not be present "Geographical coverage, Groups audience, Licence, Logo, Search keywords, Site featured, State, Topic, Type of document"
    When I press "Update"
    Then I should see the success message "Document Testowy dokument has been updated."
    And I should see the following lines of text:
      | Test. dok.                            |
      | Testowy dokument                      |
      | To jest polskie tłumaczenie dokumentu |
      | Opis pliku dokumentu                  |

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowy dokument"
    When I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowany dokument"
    And I press "Update"
    Then I should see the success message "Document Zaktualizowany dokument has been updated."
    And I should not see the heading "Testowy dokument"
    And I should see the heading "Zaktualizowany dokument"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowany dokument?"
    When I press "Delete Polish translation"
    Then I should see the success message "Polish translation of the Document Zaktualizowany dokument has been deleted."
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      | bundle     |
      | Flora Mod | collection |
      | Lee Facil | collection |
      | Flora Mod | solution   |
      | Lee Facil | solution   |

  Scenario Outline: Users who do not have edit access cannot manage translations.
    Given users:
      | Username    | Roles     |
      | John Member |           |
      | Ross Auth   |           |
    Given the following <bundle> user memberships:
      | <bundle>      | user        | roles |
      | Test <bundle> | John Member |       |
    And document content:
      | title         | <bundle>      |
      | Test document | Test <bundle> |
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "Test document" document
    Then I should see the heading "Access denied"

    Examples:
      | user        | bundle     |
      | John Member | collection |
      | Ross Auth   | collection |
      | John Member | solution   |
      | Ross Auth   | solution   |
