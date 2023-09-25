@api @group-b
Feature: Notifications are muted when entity is archived/unarchived.
  As a member of a collection
  I dont want to receive a notification when an entity is archived or unarchived.

  Background:
    Given the following collections:
      | title     | state     |
      | Milky Way | published |
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
      | collection | user   | subscriptions                                 |
      | Milky Way  | hristo | discussion, discussion, event, news, solution |
      | Milky Way  | bisera | discussion, document, news                    |
      | Milky Way  | kalin  | document, event                               |

  Scenario Outline: Updates to archive and unarchive state does not trigger notifications.
    Given <type> content:
      | title            | state     | collection | body | content | headline    |
      | Milky Way <type> | published | Milky Way  | Test | Test    | Test <type> |
    And I mark all emails as read
    When I am logged in as "bisera"
    And I go to the edit form of the "Milky Way <type>" <type>
    And I press "Archive"

    Examples:
      | type     |
      | document |
      | event    |
      | news     |
