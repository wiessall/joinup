@api @group-g
Feature: When a solution is archived then its sub-content also should be archived.

  Scenario: Archiving a solution must archive its sub-content.
    Given the following collection:
      | title | Collection |
      | state | published  |
    And owner:
      | name        | type    |
      | The Company | Company |
    And contact:
      | name  | Liam Smith             |
      | email | liam.smith@example.com |
    And contact:
      | name  | Jennifer Unconfirmed       |
      | email | jennifer.smith@example.com |
    And solution:
      | title               | Solution                         |
      | collection          | Collection                       |
      | state               | published                        |
      | description         | Description                      |
      | solution type       | Innovation                       |
      | creation date       | 2018-11-19T10:00                 |
      | modification date   | 2018-12-19T10:00                 |
      | owner               | The Company                      |
      | contact information | Jennifer Unconfirmed, Liam Smith |
    And "event" content:
      | title          | solution | state     |
      | Solution Event | Solution | published |
    And "news" content:
      | title           | solution | state     |
      | Solution News 1 | Solution | published |
      | Solution News 2 | Solution | draft     |
      | Solution News 3 | Solution | published |
    And the workflow state of the "Solution News 3" node is changed to "needs_update"
    And "document" content:
      | title             | collection | state     |
      | Solution Document | Solution   | published |
    And "discussion" content:
      | title               | collection | state     |
      | Solution Discussion | Solution   | published |
    And distribution:
      | title  | Solution Distribution |
      | parent | Solution              |
      | state  | published             |
    And releases:
      | title              | is version of | state     |
      | Solution Release 1 | Solution      | published |
      | Solution Release 2 | Solution      | draft     |
      | Solution Release 3 | Solution      | published |
    And the workflow state of the "Solution Release 3" "rdf_entity" is changed to "needs_update"
    Given I am logged in as a user with the moderator role and I have the following fields:
      | name                   | John The Moderator             |
      | mail                   | john.the.moderator@example.com |
      | field_user_first_name  | John                           |
      | field_user_family_name | Moderator                      |
    And the queue "solution_archive_related_content" is empty
    When I go to the edit form of the "Solution" solution
    Then I should see the button "Archive"
    When I press "Archive"
    Then I should see the following success messages:
      | Success messages                                                            |
      | The archiving process of the Solution has been initiated.                   |
      | You will receive an email notification as soon as the process is completed. |
    And the queue "solution_archive_related_content" should have 1 item
    Given the following contact information emails are validated:
      | Liam Smith |
    And I mark all emails as read
    Given the queue "solution_archive_related_content" is executed
    And the workflow state of the "Solution Event" content should be "archived"
    And the workflow state of the "Solution News 1" content should be "archived"
    And the workflow state of the "Solution News 2" content should be "draft"
    And the workflow state of the "Solution News 3" content should be "archived"
    And the workflow state of the "Solution Document" content should be "archived"
    And the workflow state of the "Solution Discussion" content should be "archived"
    And the workflow state of the "Solution Distribution" "rdf entity" should be "archived"
    And the workflow state of the "Solution Release 1" "rdf entity" should be "archived"
    And the workflow state of the "Solution Release 2" "rdf entity" should be "draft"
    And the workflow state of the "Solution Release 3" "rdf entity" should be "archived"
    And the queue "solution_archive_related_content" should have 0 items
    And 2 e-mails should have been sent
    And the following email should have been sent:
      | recipient | John The Moderator                 |
      | subject   | Joinup: Solution has been archived |
    And the email sent to "John The Moderator" with subject "Joinup: Solution has been archived" contains the following lines of text:
      | Dear John,                                                           |
      | The Solution, along with its associated content items, has been      |
      | archived. Even though the Solution has been archived, you can still  |
      | access its pages through the Advanced Search page by selecting 'Show |
      | only Archived items' or through the Collection it belonged to.       |
      | If you think this action is not clear, please contact Joinup Support |
      | at                                                                   |
    # The email sent to Liam (the contact person of the solution) won't contain
    # the "Dear Liam," greeting since Liam does not have a user account on
    # Joinup.
    And the email sent to "liam.smith@example.com" with subject "Joinup: Solution has been archived" contains the following lines of text:
      | The Solution, along with its associated content items, has been      |
      | archived. Even though the Solution has been archived, you can still  |
      | access its pages through the Advanced Search page by selecting 'Show |
      | only Archived items' or through the Collection it belonged to.       |
      | If you think this action is not clear, please contact Joinup Support |
      | at                                                                   |
