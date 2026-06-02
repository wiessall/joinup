@api @group-i
Feature: When a collection is archived then its sub-content also should be archived.

  Scenario: Unarchiving a collection must unarchive its sub-content.
    Given the following users:
      | Username       | E-mail                     | First name | Family name | Roles     |
      | John Moderator | john.moderator@example.com | John       | Moderator   | moderator |
      | Karl Owner     | karl.owner@example.com     | Karl       | Owner       |           |
    And the following "collection" content:
      | title           | abstract        | description     | logo     | topic                   | state     |
      | Sample Cluster  | Sample Cluster  | Sample Cluster  | logo.png | Statistics and Analysis | published |
    And the following collection user memberships:
      | collection     | user       | roles              |
      | Sample Cluster | Karl Owner | owner, facilitator |
    And the following "solution" content:
      | title           | collection      | state     |
      | Archived Soln 1 | Sample Cluster  | published |
      | Archived Soln 2 | Sample Cluster  | published |
    And the workflow state of the "Archived Soln 1" content is changed to "archived"
    And the workflow state of the "Archived Soln 2" content is changed to "archived"
    And the following "news" content:
      | title              | collection      | state     |
      | Cluster News 1     | Sample Cluster  | published |
      | Cluster News Draft | Sample Cluster  | draft     |
    And the workflow state of the "Cluster News 1" content is changed to "archived"
    And the following "event" content:
      | title              | collection      | state     |
      | Cluster Event 1    | Sample Cluster  | published |
    And the workflow state of the "Cluster Event 1" content is changed to "archived"
    And the following "discussion" content:
      | title              | collection      | state     |
      | Cluster Discussion | Sample Cluster  | published |
    And the workflow state of the "Cluster Discussion" content is changed to "archived"
    And the following "document" content:
      | title              | collection      | state     |
      | Cluster Document   | Sample Cluster  | published |
    And the workflow state of the "Cluster Document" content is changed to "archived"
    And the following "custom_page" content:
      | title             | collection      | state     |
      | Cluster Page 1    | Sample Cluster  | published |
      | Cluster Page Drft | Sample Cluster  | draft     |
    And the workflow state of the "Cluster Page 1" content is changed to "archived"
    # Bring the collection into the archived state via the existing flow.
    And the workflow state of the "Sample Cluster" content is changed to "archived"

    Given I am logged in as "John Moderator"
    And the "group_archive_related_content" queue is empty

    When I go to the edit form of the "Sample Cluster" collection
    Then I should see the button "Unarchive"

    When I press "Unarchive"
    Then I should see the following success messages:
      | Success messages                                              |
      | The unarchiving process of the Collection has been initiated. |
    And the number of items in the "group_archive_related_content" queue should be 1

    # Execute the queue.
    Given I run the "group_archive_related_content" queue worker
    Then the number of items in the "group_archive_related_content" queue should be 0
    And the workflow state of the "Cluster News 1" content should be "published"
    And the workflow state of the "Cluster News Draft" content should be "draft"
    And the workflow state of the "Cluster Event 1" content should be "published"
    And the workflow state of the "Cluster Discussion" content should be "published"
    And the workflow state of the "Cluster Document" content should be "published"
    And the workflow state of the "Cluster Page 1" content should be "published"
    And the workflow state of the "Cluster Page Drft" content should be "draft"
    # The cascade unarchives the solutions of the collection too, which in turn
    # triggers their own sub-content unarchive queue.
    And the workflow state of the "Archived Soln 1" content should be "published"
    And the workflow state of the "Archived Soln 2" content should be "published"

    When I go to the homepage of the "Sample Cluster" collection
    Then I should not see the text "Archived" in the "Header"
