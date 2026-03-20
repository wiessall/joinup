@api @group-a
Feature: Translating custom pages

  Background:
    Given users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   |           |
      | John Member |           |
      | Ross Auth   |           |
    And collection content:
      | title           |
      | Test collection |
    And solution content:
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
    And custom_page content:
      | title                       | collection      | solution      | field_paragraphs_body            | field_hide_title |
      | Test collection custom page | Test collection |               | This is test custom page content | 0                |
      | Test solution custom page   |                 | Test solution | This is test custom page content | 0                |

  Scenario Outline: Test translation access.
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "<custom page>" custom_page
    Then the response status code should be <code>

    Examples:
      | user        | custom page                 | code |
      | Flora Mod   | Test collection custom page | 200  |
      | Lee Facil   | Test collection custom page | 200  |
      | Flora Mod   | Test solution custom page   | 200  |
      | Lee Facil   | Test solution custom page   | 200  |
      | John Member | Test collection custom page | 403  |
      | Ross Auth   | Test collection custom page | 403  |
      | John Member | Test solution custom page   | 403  |
      | Ross Auth   | Test solution custom page   | 403  |

  Scenario: Test creating node translation from UI.
    Given I am logged in as "Flora Mod"
    # Assert language select options/value.
    When I go to the edit form of the "Test collection custom page" custom_page
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"

    # Create translation.
    When I go to the homepage of the "Test collection custom page" custom_page
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test collection custom page"
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
