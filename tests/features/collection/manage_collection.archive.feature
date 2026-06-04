@api @group-i
Feature: Archiving and unarchiving a collection cascades to its sub-content.

  Scenario: Archiving then unarchiving a collection cascades to its sub-content while leaving its solutions archived.
    Given the following users:
      | Username       | Roles     |
      | John Moderator | moderator |
    And the following "collection" content:
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
