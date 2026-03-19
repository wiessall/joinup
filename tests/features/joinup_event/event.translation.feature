@api @group-a
Feature: Translating events

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
    And event content:
      | title      | <bundle>      |
      | Test event | Test <bundle> |

    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "Test event" event
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    # Create translation.
    When I go to the homepage of the "Test event" event
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test event"
    When I fill in "Title" with "Testowe wydarzenie"
    And I fill in "Abstract" with "Opis wydarzenia"
    And I fill in "Agenda" with "Plan wydarzenia"
    Then the following fields should not be present "Attachments, Body, Comments, Contact email, Date, Geographical coverage, Geographic coordinates, Groups audience, Logo, Organisation, Organisation type, Physical location, Scope, Search keywords, Short title, Site featured, State, Topic, Virtual location,  Website"
    When I press "Update"
    Then I should see the success message "Event Testowe wydarzenie has been updated."
    And I should see the heading "Testowe wydarzenie"
    And I should see "Plan wydarzenia"

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowe wydarzenie"
    When I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowane wydarzenie"
    And I press "Update"
    Then I should see the success message "Event Zaktualizowane wydarzenie has been updated."
    And I should not see the heading "Testowe wydarzenie"
    And I should see the heading "Zaktualizowane wydarzenie"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowane wydarzenie?"
    When I press "Delete Polish translation"
    # @todo Inconsistent messages, see ISAICP-10959
    Then I should see the success message "Polish translation of the Event Zaktualizowane wydarzenie has been deleted."
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
    And event content:
      | title      | <bundle>      |
      | Test event | Test <bundle> |

    Given I am logged in as "<user>"
    When I visit the translation overview form of the "Test <bundle>" <bundle>
    Then I should see the heading "Access denied"

    Examples:
      | user        | bundle     |
      | John Member | collection |
      | Ross Auth   | collection |
      | John Member | solution   |
      | Ross Auth   | solution   |
