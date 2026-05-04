@api @group-i
Feature: Creating content and commenting on archived collection
  In order to not waste time on maintaining legacy collections
  As a collection owner
  I want to be able to archive old collections

  Background: Check access to the Post comment form
    Given users:
      | Username    | Roles     |
      | Flora Hunt  | moderator |
      | Lee Reeves  |           |
      | Karl Fields |           |
    And the following "collection" content:
      | title               | abstract            | description         | logo     | topic                   |
      | The Willing Consort | The Willing Consort | The Willing Consort | logo.png | Statistics and Analysis |
    Given the following "solution" content:
      | title               | collection          |
      | The Loyal Companion | The Willing Consort |
    And the following collection user memberships:
      | collection          | user        | roles              |
      | The Willing Consort | Karl Fields | owner, facilitator |

  Scenario Outline: 'Comment form' should not be accessible on an archived collection content.
    Given <content_type> content:
      | title            | collection   | solution   |
      | Commented entity | <collection> | <solution> |
    When I am logged in as "Lee Reeves"
    And I visit the "Commented entity" <content_type>
    And I should see a ".comment-form" element

    # Archive the collection.
    When I am logged in as "Karl Fields"
    And I go to the edit form of the "The Willing Consort" collection
    And I press "Request archival"
    And I am logged in as a moderator
    And I go to the edit form of the "The Willing Consort" collection
    When I fill in "Motivation" with "As you wish."
    And I press "Archive"
    And I visit the "Commented entity" <content_type>
    And I should not see a ".comment-form" element

    When I am logged in as "Lee Reeves"
    And I visit the "Commented entity" <content_type>
    And I should not see a ".comment-form" element

    When I am not logged in
    And I visit the "Commented entity" <content_type>
    And I should not see a ".comment-form" element

    Examples:
      | content_type | collection          | solution            |
      | discussion   | The Willing Consort |                     |
      | news         | The Willing Consort |                     |
      | discussion   |                     | The Loyal Companion |
      | news         |                     | The Loyal Companion |

  Scenario: 'Add community content' menu items should not be visible in the archived connection.
    When I am logged in as "Karl Fields"
    And I go to the "The Willing Consort" collection
    And I click "Edit"
    And I press "Request archival"
    And I am logged in as a moderator
    And I go to the "The Willing Consort" collection
    And I click "Edit"
    When I fill in "Motivation" with "As you wish."
    And I press "Archive"

    # We only need to check that privileged users do not have access anymore.
    And I am logged in as a facilitator of the "The Willing Consort" collection
    And I go to the "The Willing Consort" collection
    Then I should not see the plus button menu
