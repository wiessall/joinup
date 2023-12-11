@api @group-c
Feature: Content archival.

  Scenario Outline: All community content can be archived any time.
    Given collection:
      | title      | Some collection |
      | state      | published       |
      # Set the moderation to yes to be able to access all states.
      # Deletion request is not available in a post moderation workflow.
      | moderation | yes             |
    Given <type> content:
      | title       | state   | collection      | body | content | headline    | changed   |
      | Test <type> | <state> | Some collection | Test | Test    | Test <type> | <changed> |
    When I am logged in as a moderator
    And I go to the edit form of the "Test <type>" <type>
    Then I should see the button "Archive"

    When I press "Archive"
    Then I should see the message "<type label> Test <type> has been updated."
    And I should see the text "Archive" in the "Page title" region
    And I should see "Last update: <visible>"
    When I go to the edit form of the "Test <type>" <type>
    Then I should not see the button "Archive"
    And I should see the button "Unarchive"

    When I press "Unarchive"
    Then I should see the message '<type label> "Test <type>" has been unarchived.'
    And I should see "Last update: <visible>"
    And I should not see the text "Archive" in the "Page title" region
    When I go to the edit form of the "Test <type>" <type>
    Then I should see the button "Archive"
    And the current workflow state should be "<state label>"

    Examples:
      | type       | type label | state            | state label      | changed          | visible    |
      | discussion | Discussion | draft            | Draft            | 2019-11-19 20:00 | 19/11/2019 |
      | discussion | Discussion | proposed         | Proposed         | 2018-11-18 20:00 | 18/11/2018 |
      | discussion | Discussion | published        | Published        | 2017-11-17 20:00 | 17/11/2017 |
      | discussion | Discussion | needs_update     | Needs update     | 2016-11-16 20:00 | 16/11/2016 |
      | discussion | Discussion | deletion_request | Deletion request | 2015-11-15 20:00 | 15/11/2015 |
      | document   | Document   | draft            | Draft            | 2019-11-14 20:00 | 14/11/2019 |
      | document   | Document   | proposed         | Proposed         | 2019-11-13 20:00 | 13/11/2019 |
      | document   | Document   | published        | Published        | 2019-11-12 20:00 | 12/11/2019 |
      | document   | Document   | needs_update     | Needs update     | 2019-11-11 20:00 | 11/11/2019 |
      | document   | Document   | deletion_request | Deletion request | 2019-11-10 20:00 | 10/11/2019 |
      | event      | Event      | draft            | Draft            | 2019-12-19 20:00 | 19/12/2019 |
      | event      | Event      | proposed         | Proposed         | 2019-11-19 20:00 | 19/11/2019 |
      | event      | Event      | published        | Published        | 2019-11-18 20:00 | 18/11/2019 |
      | event      | Event      | needs_update     | Needs update     | 2019-11-17 20:00 | 17/11/2019 |
      | event      | Event      | deletion_request | Deletion request | 2019-11-16 20:00 | 16/11/2019 |
      | news       | News       | draft            | Draft            | 2019-11-15 20:00 | 15/11/2019 |
      | news       | News       | proposed         | Proposed         | 2019-11-14 20:00 | 14/11/2019 |
      | news       | News       | published        | Published        | 2019-11-13 20:00 | 13/11/2019 |
      | news       | News       | needs_update     | Needs update     | 2019-11-12 20:00 | 12/11/2019 |
      | news       | News       | deletion_request | Deletion request | 2019-11-11 20:00 | 11/11/2019 |

  Scenario Outline: Group facilitators can not unarchived community content
    Given <group type>:
      | title      | Some group |
      | state      | published  |
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
      | collection | discussion | Discussion | published        |
      | collection | discussion | Discussion | needs_update     |
      | collection | discussion | Discussion | deletion_request |
      | collection | document   | Document   | proposed         |
      | collection | document   | Document   | published        |
      | collection | document   | Document   | needs_update     |
      | collection | document   | Document   | deletion_request |
      | collection | event      | Event      | proposed         |
      | collection | event      | Event      | published        |
      | collection | event      | Event      | needs_update     |
      | collection | event      | Event      | deletion_request |
      | collection | news       | News       | proposed         |
      | collection | news       | News       | published        |
      | collection | news       | News       | needs_update     |
      | collection | news       | News       | deletion_request |
      | solution   | discussion | Discussion | proposed         |
      | solution   | discussion | Discussion | published        |
      | solution   | discussion | Discussion | needs_update     |
      | solution   | discussion | Discussion | deletion_request |
      | solution   | document   | Document   | proposed         |
      | solution   | document   | Document   | published        |
      | solution   | document   | Document   | needs_update     |
      | solution   | document   | Document   | deletion_request |
      | solution   | event      | Event      | draft            |
      | solution   | event      | Event      | proposed         |
      | solution   | event      | Event      | published        |
      | solution   | event      | Event      | needs_update     |
      | solution   | event      | Event      | deletion_request |
      | solution   | news       | News       | draft            |
      | solution   | news       | News       | proposed         |
      | solution   | news       | News       | published        |
      | solution   | news       | News       | needs_update     |
      | solution   | news       | News       | deletion_request |
