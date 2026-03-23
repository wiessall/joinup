@api @group-j
Feature: Translating news

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
    And news content:
      | title                | collection      | solution      | field_paragraphs_body     |
      | Test collection news | Test collection |               | This is test news content |
      | Test solution news   |                 | Test solution | This is test news content |

  Scenario Outline: Test translation access.
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "<title>" news
    Then the response status code should be <code>

    Examples:
      | user        | title                | code |
      | Flora Mod   | Test collection news | 200  |
      | Lee Facil   | Test collection news | 200  |
      | Flora Mod   | Test solution news   | 200  |
      | Lee Facil   | Test solution news   | 200  |
      | John Member | Test collection news | 403  |
      | Ross Auth   | Test collection news | 403  |
      | John Member | Test solution news   | 403  |
      | Ross Auth   | Test solution news   | 403  |

  Scenario Outline: Test creating node translation from UI.
    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "<title>" news
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"

    # Create translation.
    When I go to the homepage of the "<title>" news
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of <title>"
    Then the following fields should be present "Headline, Short title, Abstract, Body, Add a new file, Source URL"
    Then the following fields should not be present "Collection, Comments,Geographical coverage,Logo, Referenced solution, Search keywords, Site featured, State, Topic"
    When I fill in "Headline" with "Testowy news"
    And I fill in "Abstract" with "Opis newsa"
    And I fill in "Short title" with "Krótki tytuł newsa"
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value | To jest polskie tłumaczenie newsa |
    And I attach the file "test.zip" to "Add a new file"
    And I press "Upload"
    And I fill in "File description" with "Plik testowy"
    And I press "Update"
    Then I should see the success message "News Krótki tytuł newsa has been updated."
    And I should see "Krótki tytuł newsa"
    And I should see the heading "Testowy news"
    And I should see "To jest polskie tłumaczenie newsa"
    And I should see "Plik testowy"

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Krótki tytuł newsa"
    When I click "Edit" in the "Polish" row
    And I fill in "Headline" with "Zaktualizowany news"
    And I fill in "Short title" with "Zakt. news"
    And I press "Update"
    Then I should see the success message "News Zakt. news has been updated."
    And I should not see the heading "Testowy news"
    And I should see the heading "Zaktualizowany news"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zakt. news?"
    When I press "Delete Polish translation"
    Then I should see the success message "Polish translation of the News Zakt. news has been deleted."
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      | title                |
      | Flora Mod | Test collection news |
      | Lee Facil | Test collection news |
      | Flora Mod | Test solution news   |
      | Lee Facil | Test solution news   |
