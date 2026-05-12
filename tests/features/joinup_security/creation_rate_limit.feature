@api @group-g
Feature: Content creation rate limit
  Content creation is limited dependently on role or community content created in last 24 hours.

  Background:
    Given the following "collection" content:
      | title |
      | Test  |
    And the following users:
      | Username         | Roles     | E-mail         |
      | John Moderator   | moderator |                |
      | John Facilitator |           |                |
      | John Author      |           |                |
      | John User        |           | user@gmail.com |
    And the following "news" content:
      | title            | collection | author           |
      | Moderator news   | Test       | John Moderator   |
      | Facilitator news | Test       | John Facilitator |
      | Author news      | Test       | John Author      |
      | User news        | Test       | John User        |
    And the following "event" content:
      | title             | collection | author           |
      | Moderator event   | Test       | John Moderator   |
      | Facilitator event | Test       | John Facilitator |
      | Author event      | Test       | John Author      |
    And the following "discussion" content:
      | title                  | collection | author           | created    |
      | Moderator discussion   | Test       | John Moderator   | now -2 day |
      | Facilitator discussion | Test       | John Facilitator | now -2 day |
      | Author discussion      | Test       | John Author      | now -2 day |
      | User discussion        | Test       | John User        | now -2 day |
    And the following collection user memberships:
      | collection | user             | roles       |
      | Test       | John Facilitator | facilitator |
      | Test       | John Author      | author      |
      | Test       | John User        | member      |

  Scenario Outline: Users with "bypass node creation limit" or with max 1 community content in last 24 hours can add new content.
    Given I am logged in as "<user>"
    When I go to the homepage of the "Test" collection
    And I click "Add news" in the plus button menu
    Then all submit buttons inside the "#edit-actions" element should be "enabled"
    And I should not see the message 'You are not allowed to create content today. Please, try tomorrow'

    Examples:
      | user             |
      | John Moderator   |
      | John Facilitator |
      | John Author      |
      | John User        |

  Scenario: Users without "bypass node creation limit" with over 1 community content in last 24 hours can not add new content.
    Given the following "event" content:
      | title      | collection | author    | created |
      | User event | Test       | John User | now     |

    Given I am logged in as "John User"
    When I go to the homepage of the "Test" collection
    And I click "Add news" in the plus button menu
    Then all submit buttons inside the "#edit-actions" element should be "disabled"
    And I should see the message 'You are not allowed to create content today. Please, try tomorrow'
