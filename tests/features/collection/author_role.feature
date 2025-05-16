@api @group-a
Feature:
  In order to better manage my group members
  As a group owner
  I need to be able to give extra permissions to authors.

  Scenario Outline: Authors can add content regardless of group settings.
    Given collection content:
      | title             |
      | Author collection |
    And the "content_creation" setting of the "Author collection" collection is set to <content_creation>
    And the "group_moderation" setting of the "Author collection" collection is set to <moderation>
    When I am logged in as an "author" of the "Author collection" collection
    And I go to the "Author collection" collection
    Then I should see the following links:
      | Add solution   |
      | Add news       |
      | Add discussion |
      | Add document   |
      | Add event      |

    Examples:
      | moderation | content_creation           |
      | yes        | "registered users"         |
      | yes        | "members"                  |
      | yes        | "facilitators and authors" |
      | no         | "registered users"         |
      | no         | "members"                  |
      | no         | "facilitators and authors" |
