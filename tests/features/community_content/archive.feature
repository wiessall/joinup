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
      | discussion | Discussion | published        | Published        | 2017-11-17 20:00 | 17/11/2017 |
      | document   | Document   | published        | Published        | 2019-11-12 20:00 | 12/11/2019 |
      | event      | Event      | published        | Published        | 2019-11-18 20:00 | 18/11/2019 |
      | news       | News       | published        | Published        | 2019-11-13 20:00 | 13/11/2019 |

  Scenario Outline: User should not be allowed unarchiving content of an archived solution.
    Given the following solution:
      | title | Cartridge cleaner |
      | state | archived          |
    Given <type> content:
      | title                | state    | solution          | state    |
      | Test archived <type> | archived | Cartridge cleaner | archived |

    When I am logged in as a moderator
    And I go to the edit form of the "Test archived <type>" <type>
    Then I should not see the button "Unarchive"

    Examples:
      | type       |
      | discussion |
      | document   |
      | event      |
      | news       |

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
      | collection | discussion | Discussion | published        |
      | collection | document   | Document   | published        |
      | collection | event      | Event      | published        |
      | collection | news       | News       | published        |
      | solution   | discussion | Discussion | published        |
      | solution   | document   | Document   | published        |
      | solution   | event      | Event      | published        |
      | solution   | news       | News       | published        |
