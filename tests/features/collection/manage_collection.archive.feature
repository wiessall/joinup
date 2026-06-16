@api @group-i
Feature: When a collection is archived its sub-content should also be archived.

  Background:
    Given the following users:
      | Username       | Roles     |
      | John Moderator | moderator |
    And the following "collection" content:
      | title      | state            |
      | Collection | archival_request |
    And the following "custom_page" content:
      | title         | collection | state     |
      | Custom page 1 | Collection | published |
      | Custom page 2 | Collection | draft     |
    And the following "news" content:
      | title  | collection | state     |
      | News 1 | Collection | published |
      | News 2 | Collection | draft     |

  Scenario: Archiving a collection must archive its sub-content.
    Given I am logged in as "John Moderator"
    And the "group_archive_related_content" queue is empty
    When I go to the edit form of the "Collection" collection
    And I fill in "Motivation" with "Archiving collection."
    And I press "Archive"
    Then I should see the following success messages:
      | Success messages                                                            |
      | The archiving process of the Collection has been initiated.                 |
      | You will receive an email notification as soon as the process is completed. |
    And the number of items in the "group_archive_related_content" queue should be 1

    # Execute the queue.
    Given I run the "group_archive_related_content" queue worker
    Then the number of items in the "group_archive_related_content" queue should be 0
    And the workflow state of the "Custom page 1" content should be "archived"
    And the workflow state of the "Custom page 2" content should be "draft"
    And the workflow state of the "News 1" content should be "archived"
    And the workflow state of the "News 2" content should be "draft"
