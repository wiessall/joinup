@api @group-j
Feature: Translating releases

  Background:
    Given the following users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   | developer |
      | John Member |           |
      | Ross Auth   |           |
    And the following "solution" content:
      | title         |
      | Test solution |
    And the following "release" content:
      | title        | is version of |
      | Test release | Test solution |
    And the following solution user memberships:
      | solution      | user        | roles       |
      | Test solution | Lee Facil   | facilitator |
      | Test solution | John Member |             |

  Scenario Outline: Test translation access.
    Given I am logged in as "<user>"
    When I visit the translation overview form of the "<title>" release
    Then the response status code should be <code>

    Examples:
      | user        | title        | code |
      | Flora Mod   | Test release | 200  |
      | Lee Facil   | Test release | 200  |
      | John Member | Test release | 403  |
      | Ross Auth   | Test release | 403  |

  Scenario Outline: Test creating node translation from UI.
    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "Test release" release
    # @wip: The node original language selector is not yet functional on this
    # branch. @see ISAICP-11330
    # Then the available options in the "Language" select should match the site languages
    # And the selected option in the "Language" select should be "en"

    # Create translation.
    When I go to the homepage of the "Test release" release
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test release"
    Then the following fields should be present "Name, Release notes"
    Then the following fields should not be present "Documentation, Geographical coverage, Is version of, Language, Release version, Search keywords, Solution data, State, Status"
    When I fill in "Name" with "Testowe wydanie"
    And I fill in "Release notes" with "Notatki do Testowego wydania"
    When I press "Save (all translations)"
    Then I should see the success message "Release Testowe wydanie has been updated."
    And I should see the following lines of text:
      | Testowe wydanie              |
      | Notatki do Testowego wydania |

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see the text "Testowe wydanie"
    When I click "Edit" in the "Polish" row
    And I fill in "Name" with "Zaktualizowane wydanie"
    And I press "Save (all translations)"
    Then I should see the success message "Release Zaktualizowane wydanie has been updated."
    And I should not see the text "Testowe wydanie"
    And I should see the text "Zaktualizowane wydanie"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see the text "Are you sure you want to delete the Polish translation of the content item Zaktualizowane wydanie?"
    When I press "Delete Polish translation"
    #Then I should see the success message "<delete message>"
    When I click "Translate" in the "Entity actions" region
    Then I should not see the text "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      | delete message                                                               |
      | Flora Mod | Polish translation of the Release Zaktualizowane wydanie has been deleted.   |
      | Lee Facil | The content item Zaktualizowane wydanie Polish translation has been deleted. |

  # Regression test: Notifications should not be sent when tampering with
  # translations.
  Scenario: Translating a published release does not trigger update notifications.
    Given the following users:
      | Username     | E-mail                   | First name | Family name | Roles     |
      | Olivia Owner | olivia.owner@example.com | Olivia     | Owner       |           |
      | Fred Facil   | fred.facil@example.com   | Fred       | Facil       |           |
      | Morgan Mod   | morgan.mod@example.com   | Morgan     | Mod         | moderator |
    And the following "solution" content:
      | title    |
      | Polyglot |
    And the following "release" content:
      | title        | is version of | release number | state     |
      | Polyglot 1.0 | Polyglot      | 1.0            | published |
    And the following solution user memberships:
      | solution | user         | roles       |
      | Polyglot | Olivia Owner | owner       |
      | Polyglot | Fred Facil   | facilitator |

    # A normal update of the source release does notify the other privileged
    # members, proving the notification path is wired up and active.
    Given I am logged in as "Fred Facil"
    And I mark all emails as read
    When I go to the edit form of the "Polyglot 1.0" release
    And I fill in "Release notes" with "Updated release notes."
    And I press "Update"
    Then the following email should have been sent:
      | recipient | Morgan Mod                                              |
      | subject   | Interoperable Europe Portal: A release has been updated |

    # Creating a translation of the same published release must not notify
    # anyone, even though it is technically a save of the (published) release.
    Given I am logged in as "Morgan Mod"
    And I mark all emails as read
    When I go to the homepage of the "Polyglot 1.0" release
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Polyglot 1.0"
    When I fill in "Name" with "Poliglota 1.0"
    And I fill in "Release notes" with "Notatki do wydania."
    And I press "Save (all translations)"
    Then I should see the success message "Release Poliglota 1.0 has been updated."
    And 0 e-mails should have been sent

    # Editing the existing translation must not notify anyone either.
    Given I mark all emails as read
    When I click "Translate" in the "Entity actions" region
    And I click "Edit" in the "Polish" row
    And I fill in "Release notes" with "Zaktualizowane notatki."
    And I press "Save (all translations)"
    Then I should see the success message "Release Poliglota 1.0 has been updated."
    And 0 e-mails should have been sent

  # @wip: switches language via the Top Bar language selector, which is not yet
  # functional on this branch. @see ISAICP-11330
  @wip
  Scenario: Default language of new node should be same as language of the interface
    Given the following "solution" content:
      | title           |
      | Test solution 2 |
    Given I am logged in as a moderator
    When I go to the "Test solution 2" solution
    And I click "Add release" in the plus button menu
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"
    When I switch to the "pl" language
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "pl"
