@api @group-a
Feature: Translating custom pages

  Background:
    Given users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   |           |
      | John Member |           |
      | Ross Auth   |           |
    Given collection content:
      | title           |
      | Test collection |
    Given solution content:
      | title         |
      | Test solution |

  Scenario Outline: Users who do have edit access can manage translations.
    Given the following <bundle> user memberships:
      | <bundle>      | user        | roles       |
      | Test <bundle> | Lee Facil   | facilitator |
      | Test <bundle> | John Member |             |
    And custom_page content:
      | title            | <bundle>      | field_hide_title | field_paragraphs_body     |
      | Test custom page | Test <bundle> | 0                | This is test news content |

    Given I am logged in as "<user>"
    # Assert language select options/value.
    When I go to the edit form of the "Test custom page" custom_page
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    # Create translation.
    When I go to the homepage of the "Test custom page" custom_page
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test custom page"
    Then the following fields should be present "Title, Abstract, Add a new file"
    Then the following fields should not be present "Group, Hide title, Logo, Search keywords, State, Topic"
    When I fill in "Title" with "Testowa strona niestandardowa"
    And I fill in "Abstract" with "Opis strony niestandardowej"
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value | To jest polskie tłumaczenie strony |
    And I attach the file "test.zip" to "Add a new file"
    And I press "Update"
    Then I should see the following lines of text:
      | The Attachments field description is required. |
    And I fill in "Description" with "Plik testowy"
    And I press "Update"
    Then I should see the success message "Custom page Testowa strona niestandardowa has been updated."
    And I should see the heading "Testowa strona niestandardowa"
    And I should see "To jest polskie tłumaczenie strony"
    And I should see "Plik testowy"

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowa strona niestandardowa"
    When I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowana strona"
    And I press "Update"
    Then I should see the success message "Custom page Zaktualizowana strona has been updated."
    And I should not see the heading "Testowa strona niestandardowa"
    And I should see the heading "Zaktualizowana strona"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowana strona?"
    When I press "Delete Polish translation"
    Then I should see "Polish translation of the Custom page Zaktualizowana strona has been deleted."
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
    Given the following <bundle> user memberships:
      | <bundle>      | user        | roles |
      | Test <bundle> | John Member |       |
    And custom_page content:
      | title            | <bundle>      |
      | Test custom page | Test <bundle> |

    Given I am logged in as "<user>"
    When I visit the translation overview form of the "Test custom page" custom_page
    Then I should see the heading "Access denied"

    Examples:
      | user        | bundle     |
      | John Member | collection |
      | Ross Auth   | collection |
      | John Member | solution   |
      | Ross Auth   | solution   |
