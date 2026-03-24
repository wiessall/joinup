@api @group-j
Feature: Translating events

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
    And event content:
      | title                 | collection      | solution      | field_paragraphs_body      |
      | Test collection event | Test collection |               | This is test event content |
      | Test solution event   |                 | Test solution | This is test event content |

  Scenario Outline: Test translation access.
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "<title>" event
    Then the response status code should be <code>

    Examples:
      | user        | title                 | code |
      | Flora Mod   | Test collection event | 200  |
      | Lee Facil   | Test collection event | 200  |
      | Flora Mod   | Test solution event   | 200  |
      | Lee Facil   | Test solution event   | 200  |
      | John Member | Test collection event | 403  |
      | Ross Auth   | Test collection event | 403  |
      | John Member | Test solution event   | 403  |
      | Ross Auth   | Test solution event   | 403  |

  Scenario Outline: Test creating node translation from UI.
    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "<title>" event
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    # Create translation.
    When I go to the homepage of the "<title>" event
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of <title>"
    Then the following fields should be present "Title, Abstract, Agenda, Body, Add a new file"
    Then the following fields should not be present "Comments, Contact email, Date, Geographical coverage, Geographic coordinates, Groups audience, Logo, Organisation, Organisation type, Physical location, Scope, Search keywords, Short title, Site featured, State, Topic, Virtual location,  Website"
    When I fill in "Title" with "Testowe wydarzenie"
    And I fill in "Abstract" with "Opis wydarzenia"
    And I fill in "Agenda" with "Plan wydarzenia"
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value | To jest polskie tłumaczenie wydarzenia |
    And I attach the file "test.zip" to "Add a new file"
    And I press "Upload"
    And I fill in "File description" with "Plik testowy"
    When I press "Save (all translations)"
    Then I should see the success message "Event Testowe wydarzenie has been updated."
    And I should see the heading "Testowe wydarzenie"
    And I should see "Plan wydarzenia"

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowe wydarzenie"
    When I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowane wydarzenie"
    And I press "Save (all translations)"
    Then I should see the success message "Event Zaktualizowane wydarzenie has been updated."
    And I should not see the heading "Testowe wydarzenie"
    And I should see the heading "Zaktualizowane wydarzenie"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowane wydarzenie?"
    When I press "Delete Polish translation"
    Then I should see the success message "Polish translation of the Event Zaktualizowane wydarzenie has been deleted."
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      | title                 |
      | Flora Mod | Test collection event |
      | Lee Facil | Test collection event |
      | Flora Mod | Test solution event   |
      | Lee Facil | Test solution event   |
