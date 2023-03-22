@api @group-b
Feature: Notifications are muted when entity is archived/unarchived.
  As a member of a collection
  I dont want to receive a notification when an entity is archived or unarchived.

  Background:
    Given the following collections:
      | title     | state     |
      | Milky Way | validated |
    And users:
      | Username | E-mail            | First name | Family name  | Notification frequency |
      | hristo   | hristo@example.bg | Hristo     | Draganov     | daily                  |
      | bisera   | bisera@example.bg | Bisera     | Kaloyancheva | weekly                 |
      | kalin    | kalin@primer.bg   | Kalin      | Antov        | monthly                |
    And the following collection user memberships:
      | collection | user   | roles       |
      | Milky Way  | hristo |             |
      | Milky Way  | bisera | owner       |
      | Milky Way  | kalin  | facilitator |
    And the following collection content subscriptions:
      | collection | user   | subscriptions                     |
      | Milky Way  | hristo | discussion, event, news, solution |
      | Milky Way  | bisera | discussion, document, news        |
      | Milky Way  | kalin  | document, event                   |

  Scenario Outline: Updates to archive and unarchive state does not trigger notifications.
    Given <type> content:
      | title            | state     | collection | body | content | headline    |
      | Milky Way <type> | validated | Milky Way  | Test | Test    | Test <type> |
    And all message digests have been delivered
    And I mark all emails as read
    When I am logged in as "bisera"
    And I go to the edit form of the "Milky Way <type>" <type>
    And I fill in "Content" with "Milky Way <type> archived content"
    And I press "Archive"
    When I go to the edit form of the "Milky Way <type>" <type>
    And I fill in "Content" with "Milky Way <type> unarchived content"
    And I press "Unarchive"
    Then 0 e-mail should have been sent
    And the daily digest for hristo should not contain any messages
    And the weekly digest for bisera should not contain any messages
    And the monthly digest for kalin should not contain any messages

    Examples:
      | type       |
      | discussion |
      | document   |
      | event      |
      | news       |