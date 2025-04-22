@api @group-g
Feature: Content creation rate limit
  Content creation is limited dependently on role or community content created in last 24 hours.

  Background:
    Given collection content:
      | title |
      | Test  |
    And users:
      | Username    | Roles     |
      | Moderator   | moderator |
      | Facilitator |           |
      | Author      |           |
      | User        |           |
    And news content:
      | title            | collection | author      |
      | Moderator news   | Test       | Moderator   |
      | Facilitator news | Test       | Facilitator |
      | Author news      | Test       | Author      |
      | User news        | Test       | User        |
    And event content:
      | title             | collection | author      |
      | Moderator event   | Test       | Moderator   |
      | Facilitator event | Test       | Facilitator |
      | Author event      | Test       | Author      |
    And discussion content:
      | title                  | collection | author      | created    |
      | Moderator discussion   | Test       | Moderator   | now -2 day |
      | Facilitator discussion | Test       | Facilitator | now -2 day |
      | Author discussion      | Test       | Author      | now -2 day |
      | User discussion        | Test       | User        | now -2 day |
    And the following collection user memberships:
      | collection | user        | roles       |
      | Test       | Facilitator | facilitator |
      | Test       | Author      | author      |
      | Test       | User        | member      |

  Scenario Outline: Anonymous user can find items
    Given I am logged in as <user>
    When I go to the homepage of the "Test" collection
    And I click "Add news" in the plus button menu
    Then all submit buttons inside the "#edit-actions" element should be "enabled"
    And I should not see the message 'You are not allowed to create content today. Please, try tomorrow'

    Examples:
      | user        |
      | Moderator   |
      | Facilitator |
      | Author      |
      | User        |

  Scenario: Anonymous user can find items
    Given event content:
      | title      | collection | author | created |
      | User event | Test       | User   | now     |

    Given I am logged in as User
    When I go to the homepage of the "Test" collection
    And I click "Add news" in the plus button menu
    Then all submit buttons inside the "#edit-actions" element should be "disabled"
    And I should see the message 'You are not allowed to create content today. Please, try tomorrow'
