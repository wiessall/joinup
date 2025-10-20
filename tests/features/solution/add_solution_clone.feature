@api @group-clone @javascript
Feature: Test adding solutions with clone data.
  In order to manage solutions
  As a moderator
  I need to be able to add "Solution" through UI.

  Scenario: Moderator can add a solution with the right language availabilities.
    Given I am logged in as a moderator
    When I go to the homepage of the "Portal support" collection
    And I click "Add solution" in the plus button menu
    Then the "Language availability" tagify autocomplete should contain the following options:
      | Bulgarian   |
      | Croatian    |
      | Czech       |
      | Danish      |
      | Dutch       |
      | English     |
      | Estonian    |
      | Finnish     |
      | French      |
      | German      |
      | Greek       |
      | Hungarian   |
      | Irish       |
      | Italian     |
      | Latvian     |
      | Lithuanian  |
      | Maltese     |
      | Polish      |
      | Portuguese  |
      | Romanian    |
      | Slovak      |
      | Slovenian   |
      | Spanish     |
      | Swedish     |
