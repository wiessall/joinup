@api @group-i
Feature: Archiving and unarchiving a collection.
  In order to not waste time on maintaining legacy collections
  As a collection owner
  I want to be able to archive old collections

  Background:
    Given the following users:
      | Username       | Roles     |
      | John Moderator | moderator |
      | Karl Fields    |           |
      | Lee Reeves     |           |

  Scenario: Archiving then unarchiving a collection cascades to its sub-content while leaving its solutions archived.
    Given the following "collection" content:
      | title      | state            |
      | Collection | archival_request |
    # An already archived solution must not block the collection archival, and
    # its archive lifecycle is managed independently from the collection.
    And the following "solution" content:
      | title    | collection | state    |
      | Solution | Collection | archived |
    And the following "custom_page" content:
      | title         | collection | state     |
      | Custom page 1 | Collection | published |
      | Custom page 2 | Collection | draft     |
    And the following "news" content:
      | title  | collection | state     |
      | News 1 | Collection | published |
      | News 2 | Collection | draft     |
    And I am logged in as "John Moderator"
    And the "group_archive_related_content" queue is empty

    # Archive the collection.
    When I go to the edit form of the "Collection" collection
    And I fill in "Motivation" with "Archiving collection."
    And I press "Archive"
    Then I should see the following success messages:
      | Success messages                                                            |
      | The archiving process of the Collection has been initiated.                 |
      | You will receive an email notification as soon as the process is completed. |
    And the number of items in the "group_archive_related_content" queue should be 1
    Given I run the "group_archive_related_content" queue worker
    Then the number of items in the "group_archive_related_content" queue should be 0
    And the workflow state of the "Custom page 1" content should be "archived"
    And the workflow state of the "Custom page 2" content should be "draft"
    And the workflow state of the "News 1" content should be "archived"
    And the workflow state of the "News 2" content should be "draft"

    # Unarchive the collection.
    Given the "group_archive_related_content" queue is empty
    When I go to the edit form of the "Collection" collection
    Then I should see the button "Unarchive"
    When I press "Unarchive"
    Then I should see the following success messages:
      | Success messages                                              |
      | The unarchiving process of the Collection has been initiated. |
    And the number of items in the "group_archive_related_content" queue should be 1
    Given I run the "group_archive_related_content" queue worker
    Then the number of items in the "group_archive_related_content" queue should be 0
    And the workflow state of the "Custom page 1" content should be "published"
    And the workflow state of the "Custom page 2" content should be "draft"
    And the workflow state of the "News 1" content should be "published"
    And the workflow state of the "News 2" content should be "draft"
    # Solutions keep their own archive lifecycle, so they stay archived.
    And the workflow state of the "Solution" content should be "archived"

    When I go to the homepage of the "Collection" collection
    Then I should not see the text "Archived" in the "Header"

  Scenario: A solution cannot be edited when its parent collection is archived.
    Given the following "collection" content:
      | title             | state    |
      | Locked collection | archived |
    And the following "solution" content:
      | title           | collection        | state    |
      | Locked solution | Locked collection | archived |
    And I am logged in as "John Moderator"
    When I go to the edit form of the "Locked solution" solution
    Then I should see the heading "Access denied"

  Scenario Outline: Collection could not be archived if it contains at least 1 non-archived solution.
    Given the following "collection" content:
      | title               | abstract            | description         | logo     | topic                   |
      | The Willing Consort | The Willing Consort | The Willing Consort | logo.png | Statistics and Analysis |
    And the following collection user memberships:
      | collection          | user        | roles              |
      | The Willing Consort | Karl Fields | owner, facilitator |
    And the following "solution" content:
      | title               | collection          | state   |
      | The Loyal Companion | The Willing Consort | <state> |
    And I am logged in as "John Moderator"
    And I go to the edit form of the "The Willing Consort" collection
    When I fill in "Motivation" with "As you wish."
    And I press "Archive"
    Then I <action> see the error message "Collection has at least one published or draft solution. Please archive all solutions first."

    Examples:
      | state     | action     |
      | published | should     |
      | draft     | should     |
      | archived  | should not |

  Scenario Outline: 'Comment form' should not be accessible on an archived collection content.
    Given the following "collection" content:
      | title               | abstract            | description         | logo     | topic                   |
      | The Willing Consort | The Willing Consort | The Willing Consort | logo.png | Statistics and Analysis |
    And the following collection user memberships:
      | collection          | user        | roles              |
      | The Willing Consort | Karl Fields | owner, facilitator |
    And the following "solution" content:
      | title               | collection          | state    |
      | The Loyal Companion | The Willing Consort | archived |
    And the following "<content_type>" content:
      | title            | collection   | solution   |
      | Commented entity | <collection> | <solution> |

    When I am logged in as "Lee Reeves"
    And I visit the "Commented entity" <content_type>
    And I should see a ".comment-form" element

    # Archive the collection.
    When I am logged in as "Karl Fields"
    And I go to the edit form of the "The Willing Consort" collection
    And I press "Request archival"
    And I am logged in as "John Moderator"
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

  Scenario: 'Add community content' menu items should not be visible in an archived collection.
    Given the following "collection" content:
      | title               | abstract            | description         | logo     | topic                   |
      | The Willing Consort | The Willing Consort | The Willing Consort | logo.png | Statistics and Analysis |
    And the following collection user memberships:
      | collection          | user        | roles              |
      | The Willing Consort | Karl Fields | owner, facilitator |
    When I am logged in as "Karl Fields"
    And I go to the "The Willing Consort" collection
    And I click "Edit"
    And I press "Request archival"
    And I am logged in as "John Moderator"
    And I go to the "The Willing Consort" collection
    And I click "Edit"
    When I fill in "Motivation" with "As you wish."
    And I press "Archive"
    Then I should see the text "Archived" in the "Header"

    # The plus button is route-gated by `_archived_group`, so it must be hidden
    # for every role that would normally see it.
    And I am logged in as "John Moderator"
    And I go to the "The Willing Consort" collection
    Then I should not see the plus button menu

    And I am logged in as "Karl Fields"
    And I go to the "The Willing Consort" collection
    Then I should not see the plus button menu

    And I am logged in as a facilitator of the "The Willing Consort" collection
    And I go to the "The Willing Consort" collection
    Then I should not see the plus button menu

  # @covers \state_machine_permissions_node_access for collection sub-content.
  Scenario Outline: Sub-content of an archived collection cannot be edited.
    Given the following "collection" content:
      | title             |
      | Locked Collection |
    And the following collection user memberships:
      | collection        | user        | roles              |
      | Locked Collection | Karl Fields | owner, facilitator |
      | Locked Collection | Lee Reeves  | facilitator        |
    And the following "custom_page" content:
      | title              | collection        | state     |
      | Locked custom page | Locked Collection | published |
    And the following "news" content:
      | title       | headline    | collection        | state     |
      | Locked news | Locked news | Locked Collection | published |
    And the following "document" content:
      | title           | collection        | state     |
      | Locked document | Locked Collection | published |
    And the following "event" content:
      | title        | collection        | state     |
      | Locked event | Locked Collection | published |
    And the following "discussion" content:
      | title             | collection        | state     |
      | Locked discussion | Locked Collection | published |
    And the workflow state of the "Locked Collection" content is changed to "archived"
    And I am logged in as "<user>"

    When I go to the edit form of the "Locked custom page" "custom page"
    Then the response status code should be 403
    When I go to the edit form of the "Locked news" news
    Then the response status code should be 403
    When I go to the edit form of the "Locked document" document
    Then the response status code should be 403
    When I go to the edit form of the "Locked event" event
    Then the response status code should be 403
    When I go to the edit form of the "Locked discussion" discussion
    Then the response status code should be 403

    Examples:
      | user           |
      | John Moderator |
      | Karl Fields    |
      | Lee Reeves     |

  Scenario: Sub-content of an archived collection remains accessible for consultation.
    Given the following "collection" content:
      | title               | abstract            | description         | logo     | topic                   |
      | Readable Collection | Readable Collection | Readable Collection | logo.png | Statistics and Analysis |
    And the following "news" content:
      | title         | headline      | body          | collection          | state     |
      | Readable news | Readable news | Readable body | Readable Collection | published |
    And the following "document" content:
      | title             | body          | collection          | state     |
      | Readable document | Readable body | Readable Collection | published |
    And the workflow state of the "Readable Collection" content is changed to "archived"

    When I am not logged in
    And I visit the "Readable news" news
    Then the response status code should be 200

    When I am logged in as "Lee Reeves"
    And I visit the "Readable document" document
    Then the response status code should be 200
