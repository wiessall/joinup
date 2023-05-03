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
      | group type | type       | type label | state            | state label      |
      | collection | discussion | Discussion | proposed         | Proposed         |
      | collection | discussion | Discussion | validated        | Published        |
      | collection | discussion | Discussion | needs_update     | Needs update     |
      | collection | discussion | Discussion | deletion_request | Deletion request |
      | collection | document   | Document   | proposed         | Proposed         |
      | collection | document   | Document   | validated        | Published        |
      | collection | document   | Document   | needs_update     | Needs update     |
      | collection | document   | Document   | deletion_request | Deletion request |
      | collection | event      | Event      | proposed         | Proposed         |
      | collection | event      | Event      | validated        | Published        |
      | collection | event      | Event      | needs_update     | Needs update     |
      | collection | event      | Event      | deletion_request | Deletion request |
      | collection | news       | News       | proposed         | Proposed         |
      | collection | news       | News       | validated        | Published        |
      | collection | news       | News       | needs_update     | Needs update     |
      | collection | news       | News       | deletion_request | Deletion request |
      | solution   | discussion | Discussion | proposed         | Proposed         |
      | solution   | discussion | Discussion | validated        | Published        |
      | solution   | discussion | Discussion | needs_update     | Needs update     |
      | solution   | discussion | Discussion | deletion_request | Deletion request |
      | solution   | document   | Document   | proposed         | Proposed         |
      | solution   | document   | Document   | validated        | Published        |
      | solution   | document   | Document   | needs_update     | Needs update     |
      | solution   | document   | Document   | deletion_request | Deletion request |
      | solution   | event      | Event      | draft            | Draft            |
      | solution   | event      | Event      | proposed         | Proposed         |
      | solution   | event      | Event      | validated        | Published        |
      | solution   | event      | Event      | needs_update     | Needs update     |
      | solution   | event      | Event      | deletion_request | Deletion request |
      | solution   | news       | News       | draft            | Draft            |
      | solution   | news       | News       | proposed         | Proposed         |
      | solution   | news       | News       | validated        | Published        |
      | solution   | news       | News       | needs_update     | Needs update     |
      | solution   | news       | News       | deletion_request | Deletion request |
