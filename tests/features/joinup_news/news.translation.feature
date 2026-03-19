@api @group-a
Feature: Translating news

  Scenario: Users who do have edit access can manage translations.
    And news content:
      | title     | field_paragraphs_body     |
      | Test news | This is test news content |

    Given I am logged in as a user with the "moderator" role

    # Assert language select options/value.
    When I go to the edit form of the "Test news" news
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    # Create translation.
    When I go to the homepage of the "Test news" news
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test news"
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
    Then I should see "News Krótki tytuł newsa has been updated."
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

  Scenario Outline: Only moderators and group facilitators can translate content.
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
      | title         | collection      |
      | Test solution | Test collection |
    Given the following <bundle> user memberships:
      | <bundle>      | user        | roles       |
      | Test <bundle> | Lee Facil   | facilitator |
      | Test <bundle> | John Member |             |
    And news content:
      | title     | <bundle>      | field_paragraphs_body     |
      | Test news | Test <bundle> | This is test news content |

    Given I am logged in as "<user>"
    When I visit the translation overview form of the "Test news" news
    Then the response status code should be <code>

    Examples:
      | user        | bundle     | code |
      | Flora Mod   | collection | 200  |
      | Lee Facil   | collection | 200  |
      | Flora Mod   | solution   | 200  |
      | Lee Facil   | solution   | 200  |
      | John Member | collection | 403  |
      | Ross Auth   | collection | 403  |
      | John Member | solution   | 403  |
      | Ross Auth   | solution   | 403  |
