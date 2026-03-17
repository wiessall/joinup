@api @group-a
Feature: Translating news

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
      | title         | collection      |
      | Test solution | Test collection |

  Scenario Outline: Users who do have edit access can manage translations.
    Given the following <bundle> user memberships:
      | <bundle>      | user        | roles       |
      | Test <bundle> | Lee Facil   | facilitator |
      | Test <bundle> | John Member |             |
    And news content:
      | title     | <bundle>      | field_paragraphs_body     |
      | Test news | Test <bundle> | This is test news content |

    Given I am logged in as "<user>"

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
    And I press "Update"
    Then I should see the following lines of text:
      | The Attachments field description is required. |
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
    And news content:
      | title     | <bundle>      |
      | Test news | Test <bundle> |
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "Test news" news
    Then I should see the heading "Access denied"

    Examples:
      | user        | bundle     |
      | John Member | collection |
      | Ross Auth   | collection |
      | John Member | solution   |
      | Ross Auth   | solution   |
