@api @group-g
Feature: Content archival.

  Scenario Outline: All community content can be archived any time.
    Given collection:
      | title      | Some collection |
      | state      | validated       |
      # Set the moderation to yes to be able to access all states.
      # Deletion request is not available in a post moderation workflow.
      | moderation | yes             |
    When I am logged in as a moderator
    Given <type> content:
      | title       | state   | collection      |
      | Test <type> | <state> | Some collection |
    When I am logged in as a moderator
    And I go to the edit form of the "Test <type>" <type>
    Then I should see the button "Archive"

    Examples:
      | type       | state            |
      | discussion | draft            |
      | discussion | proposed         |
      | discussion | validated        |
      | discussion | needs_changes    |
      | discussion | deletion_request |
      | document   | draft            |
      | document   | proposed         |
      | document   | validated        |
      | document   | needs_changes    |
      | document   | deletion_request |
      | event      | draft            |
      | event      | proposed         |
      | event      | validated        |
      | event      | needs_changes    |
      | event      | deletion_request |
      | news       | draft            |
      | news       | proposed         |
      | news       | validated        |
      | news       | needs_changes    |
      | news       | deletion_request |
