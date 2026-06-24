@api @group-clone @javascript
Feature: Solution clone features
  In order to manage and share solutions
  As a user
  I want to interact with solution features available on the clone environment.

  Scenario: Anonymous user is redirected to log in when clicking the share solution reuse button
    Given I am not logged in
    When I go to the homepage of the "Joinup source code" solution
    Then I should see the link "Share Solution reuse"
    When I click "Share Solution reuse"
    Then I should see the heading "Sign in to continue"

  Scenario: Authenticated user sees the solution pre-filled in the submission form
    Given I am logged in as a user with the "moderator" role
    When I go to the homepage of the "Joinup source code" solution
    And I click "Share Solution reuse"
    Then I should see the text "Solution"
    And the "Solution" field should contain "Joinup source code (719986)"

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
