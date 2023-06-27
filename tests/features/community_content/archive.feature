@api @group-g
Feature: Content archival.

  Scenario Outline: All community content can be archived any time.
    Given collection:
      | title      | Some collection |
      | state      | validated       |
      # Set the moderation to yes to be able to access all states.
      # Deletion request is not available in a post moderation workflow.
      | moderation | yes             |
    Given <type> content:
      | title       | state   | collection      | body | content | headline    |
      | Test <type> | <state> | Some collection | Test | Test    | Test <type> |
    When I am logged in as a moderator
    And I go to the edit form of the "Test <type>" <type>
    Then I should see the button "Archive"

    When I press "Archive"
    Then I should see the message "<type label> Test <type> has been updated."
    And I should see the text "Archive" in the "Page title" region
    When I go to the edit form of the "Test <type>" <type>
    Then I should not see the button "Archive"
    And I should see the button "Unarchive"

    When I press "Unarchive"
    Then I should see the message '<type label> "Test <type>" has been unarchived.'
    And I should not see the text "Archive" in the "Page title" region
    When I go to the edit form of the "Test <type>" <type>
    Then I should see the button "Archive"
    And the current workflow state should be "<state label>"

    Examples:
      | type       | type label | state            | state label      |
      | discussion | Discussion | draft            | Draft            |
      | discussion | Discussion | proposed         | Proposed         |
      | discussion | Discussion | validated        | Published        |
      | discussion | Discussion | needs_update     | Needs update     |
      | discussion | Discussion | deletion_request | Deletion request |
      | document   | Document   | draft            | Draft            |
      | document   | Document   | proposed         | Proposed         |
      | document   | Document   | validated        | Published        |
      | document   | Document   | needs_update     | Needs update     |
      | document   | Document   | deletion_request | Deletion request |
      | event      | Event      | draft            | Draft            |
      | event      | Event      | proposed         | Proposed         |
      | event      | Event      | validated        | Published        |
      | event      | Event      | needs_update     | Needs update     |
      | event      | Event      | deletion_request | Deletion request |
      | news       | News       | draft            | Draft            |
      | news       | News       | proposed         | Proposed         |
      | news       | News       | validated        | Published        |
      | news       | News       | needs_update     | Needs update     |
      | news       | News       | deletion_request | Deletion request |

  Scenario Outline: Group facilitators can not unarchived community content
    Given <group type>:
      | title      | Some group |
      | state      | validated  |
      # Set the moderation to yes to be able to access all states.
      # Deletion request is not available in a post moderation workflow.
      | moderation | yes        |
    Given <type> content:
      | title       | state   | <group type> | body | content | headline    |
      | Test <type> | <state> | Some group   | Test | Test    | Test <type> |
    When I am logged in as a facilitator of the "Some group" <group type>
    And I go to the edit form of the "Test <type>" <type>
    Then I should see the button "Archive"

    When I press "Archive"
    Then I should see the message "<type label> Test <type> has been updated."
    When I go to the edit form of the "Test <type>" <type>
    Then I should not see the button "Archive"
    And I should not see the button "Unarchive"

    Examples:
      | group type | type       | type label | state            |
      | collection | discussion | Discussion | proposed         |
      | collection | discussion | Discussion | validated        |
      | collection | discussion | Discussion | needs_update     |
      | collection | discussion | Discussion | deletion_request |
      | collection | document   | Document   | proposed         |
      | collection | document   | Document   | validated        |
      | collection | document   | Document   | needs_update     |
      | collection | document   | Document   | deletion_request |
      | collection | event      | Event      | proposed         |
      | collection | event      | Event      | validated        |
      | collection | event      | Event      | needs_update     |
      | collection | event      | Event      | deletion_request |
      | collection | news       | News       | proposed         |
      | collection | news       | News       | validated        |
      | collection | news       | News       | needs_update     |
      | collection | news       | News       | deletion_request |
      | solution   | discussion | Discussion | proposed         |
      | solution   | discussion | Discussion | validated        |
      | solution   | discussion | Discussion | needs_update     |
      | solution   | discussion | Discussion | deletion_request |
      | solution   | document   | Document   | proposed         |
      | solution   | document   | Document   | validated        |
      | solution   | document   | Document   | needs_update     |
      | solution   | document   | Document   | deletion_request |
      | solution   | event      | Event      | draft            |
      | solution   | event      | Event      | proposed         |
      | solution   | event      | Event      | validated        |
      | solution   | event      | Event      | needs_update     |
      | solution   | event      | Event      | deletion_request |
      | solution   | news       | News       | draft            |
      | solution   | news       | News       | proposed         |
      | solution   | news       | News       | validated        |
      | solution   | news       | News       | needs_update     |
      | solution   | news       | News       | deletion_request |
