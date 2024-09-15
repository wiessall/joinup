@api @group-i
Feature: When a solution is archived then its sub-content also should be archived.

  Background:
    Given collection content:
      | title      | state     |
      | Collection | published |
    And owner content:
      | name        | owner type |
      | The Company | Company    |
    And contact_information content:
      | name                 | email                      |
      | Liam Smith           | liam.smith@example.com     |
      | Jennifer Unconfirmed | jennifer.smith@example.com |
    And solution content:
      | title    | collection | state     | abstract | description | topic      | solution type | creation date    | modification date | owner       | contact information              |
      | Solution | Collection | published | Abstract | Description | Demography | Innovation    | 2018-11-19T10:00 | 2018-12-19T10:00  | The Company | Jennifer Unconfirmed, Liam Smith |
    And users:
      | Username           | E-mail                     | First name | Family name | Roles     |
      | Anne Group Admin   | anne.smith@example.com     | Anne       | Smith       |           |
      | Andrew Facilitator | andrew.smith@example.com   | Andrew     | Smith       |           |
      | Blahman            | ferners-lee@example.com    |            |             |           |
      | John Moderator     | john.moderator@example.com | John       | Moderator   | moderator |
    And the following solution user memberships:
      | solution | user               | roles                      |
      | Solution | Anne Group Admin   | administrator, facilitator |
      | Solution | Andrew Facilitator | facilitator                |
    And "event" content:
      | title          | solution | state     |
      | Solution Event | Solution | published |
    And "news" content:
      | title           | solution | state     |
      | Solution News 1 | Solution | published |
      | Solution News 2 | Solution | draft     |
      | Solution News 3 | Solution | published |
      | Solution News 4 | Solution | published |
    And the workflow state of the "Solution News 3" content is changed to "needs_update"
    And the workflow state of the "Solution News 4" content is changed to "archived"
    And "document" content:
      | title                   | collection | state     | nid |
      | Solution Document       | Solution   | published |     |
      | Weird Solution Document | Solution   | draft     | 200 |
    And the workflow state of the "Weird Solution Document" content is changed to "archived"
    And "discussion" content:
      | title               | collection | state     |
      | Solution Discussion | Solution   | published |
    And "custom_page" content:
      | title                  | solution | state     |
      | Solution Custom page 1 | Solution | published |
      | Solution Custom page 2 | Solution | draft     |
      | Solution Custom page 3 | Solution | published |
    And the workflow state of the "Solution Custom page 3" content is changed to "archived"
    And distribution content:
      | title                 | solution | state     |
      | Solution Distribution | Solution | published |
    And release content:
      | title              | is version of | state     |
      | Solution Release 1 | Solution      | published |
      | Solution Release 2 | Solution      | draft     |
      | Solution Release 3 | Solution      | published |
    And the workflow state of the "Solution Release 3" "content" is changed to "needs_update"
    And the following announcements:
      | group      | subject              | state | created    | author  |
      | Solution   | Announcement1 C1     | draft | 2005-11-05 | Blahman |
      | Collection | Some announcement C2 | draft | 2005-11-05 | Blahman |

  # @covers \Drupal\joinup_group\Access\ArchivedGroupAccessCheck::access and the
  #   `_archived_group` route access requirement for solutions.
  # @covers \joinup_search_views_query_alter but should be removed and fixed on
  #   \joinup_group_form_views_exposed_form_alter where the default value forces
  #   a wrong empty ID value to the query.
  # @see \joinup_group_form_views_exposed_form_alter.
  @loggedErrors
  Scenario: Archiving / unarchiving a solution must archive / unarchive its sub-content.
    Given I am logged in as "John Moderator"
    And the "solution_archive_related_content" queue is empty
    When I go to the edit form of the "Solution" solution
    Then I should see the button "Archive"
    When I press "Archive"
    Then I should see the following success messages:
      | Success messages                                                            |
      | The archiving process of the Solution has been initiated.                   |
      | You will receive an email notification as soon as the process is completed. |
    And the number of items in the "solution_archive_related_content" queue should be 1

    # Execute the queue.
    Given the following contact information emails are validated:
      | Liam Smith |
    And I mark all emails as read
    Given I run the "solution_archive_related_content" queue worker
    Then the number of items in the "solution_archive_related_content" queue should be 0
    And the workflow state of the "Solution Event" content should be "archived"
    And the workflow state of the "Solution News 1" content should be "archived"
    And the workflow state of the "Solution News 2" content should be "draft"
    And the workflow state of the "Solution News 3" content should be "archived"
    And the workflow state of the "Solution Document" content should be "archived"
    And the workflow state of the "Weird Solution Document" content should be "archived"
    And the workflow state of the "Solution Discussion" content should be "archived"
    And the workflow state of the "Solution Custom page 1" content should be "archived"
    And the workflow state of the "Solution Custom page 2" content should be "draft"
    And the workflow state of the "Solution Custom page 3" content should be "archived"
    And the workflow state of the "Solution Distribution" "content" should be "archived"
    And the workflow state of the "Solution Release 1" "content" should be "archived"
    And the workflow state of the "Solution Release 2" "content" should be "draft"
    And the workflow state of the "Solution Release 3" "content" should be "archived"
    And the string value of the "revision_log" field in "Solution Discussion" content should be "Updated state to 'archived' by 'solution_archive_related_content' queue, from revision ID '<placeholder>' with state 'published'."
    And 2 e-mails should have been sent
    And the following email should have been sent:
      | recipient | John Moderator                     |
      | subject   | Joinup: Solution has been archived |
    And the email sent to "John Moderator" with subject "Joinup: Solution has been archived" contains the following lines of text:
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

    When I go to the homepage of the "Solution" solution
    Then I should see the text "Archived" in the "Header"

    # Test links that are not available for archived solutions.
    Given I go to the "Solution" solution
    Then I should not see the following links:
    # From the plus button menu.
      | Add custom page     |
      | Add distribution    |
      | Add event           |
      | Add discussion      |
      | Add document        |
      | Add news            |
      | Add release         |
    # From the 3-dots menu.
      | Manage content      |
      | Moderate content    |
      | Unpublished content |

    # Announcements are accessible but we cannot add or edit any.
    When I click "Announcements" in the "Entity actions" region
    Then I should not see the link "Add announcement"
    And the "announcement list" table should be:
      | Subject          | State | Created on | Author  | Edit |
      | Announcement1 C1 | Draft | 05/11/2005 | Blahman |      |

    # The link in the header toolbar will be clicked and all announcements are
    # available there. This is to test that other announcements are not
    # accidentally affected by the edit permission restriction.
    When I click "Announcements"
    Then the "announcement list" table should be:
      | Group      | Subject              | State | Created on | Author  | Edit |
      | Solution   | Announcement1 C1     | Draft | 05/11/2005 | Blahman |      |
      | Collection | Some announcement C2 | Draft | 05/11/2005 | Blahman | edit |

    # Ensure that visiting the members page, even moderators see the
    # non-privileged version.
    When I go to the members page of "Solution"
    Then I should not see the following links:
      | Add member    |
      | Invite member |

    # Now, check the unarchive operation.
    Given I mark all emails as read
    When I go to the edit form of the "Solution" solution
    Then I should see the button "Unarchive"
    When I press "Unarchive"
    Then I should see the following success messages:
      | Success messages                                            |
      | The unarchiving process of the Solution has been initiated. |
    And the number of items in the "solution_archive_related_content" queue should be 1
    # Execute the queue.
    Given there are no preexisting log records
    Given I run the "solution_archive_related_content" queue worker
    Then 0 e-mails should have been sent
    And the number of items in the "solution_archive_related_content" queue should be 0
    And there should be a record of severity warning in the log with description "The Content entity with id '200' cannot be unarchived: cannot find the previous revision it was archived from."
    And the workflow state of the "Solution Event" content should be "published"
    And the workflow state of the "Solution News 1" content should be "published"
    And the workflow state of the "Solution News 2" content should be "draft"
    And the workflow state of the "Solution News 3" content should be "published"
    And the workflow state of the "Solution News 4" content should be "published"
    And the workflow state of the "Solution Document" content should be "published"
    And the workflow state of the "Weird Solution Document" content should be "archived"
    And the workflow state of the "Solution Discussion" content should be "published"
    And the workflow state of the "Solution Custom page 1" content should be "published"
    And the workflow state of the "Solution Custom page 2" content should be "draft"
    And the workflow state of the "Solution Custom page 3" content should be "published"
    And the workflow state of the "Solution Distribution" "content" should be "published"
    And the workflow state of the "Solution Release 1" "content" should be "published"
    And the workflow state of the "Solution Release 2" "content" should be "draft"
    And the workflow state of the "Solution Release 3" "content" should be "published"
    And the string value of the "revision_log" field in "Solution Discussion" content should be "Updated state to 'published' by 'solution_archive_related_content' queue, from revision ID '<placeholder>' with state 'archived'."

    When I go to the homepage of the "Solution" solution
    Then I should not see the text "Archived" in the "Header"

  Scenario Outline: Users must not be able to edit the sub-content of an archived solution
    Given the workflow state of the "Solution" "rdf_entity" is changed to "archived"
    And I am logged in as "<User>"

    When I go to the homepage of the "Solution" solution
    Then I should see the text "Archived" in the "Header"
    When I go to the edit form of the "Solution Event" event
    Then the response status code should be 403
    When I go to the edit form of the "Solution News 1" news
    Then the response status code should be 403
    When I go to the edit form of the "Solution News 2" news
    Then the response status code should be 403
    When I go to the edit form of the "Solution News 3" news
    Then the response status code should be 403
    When I go to the edit form of the "Solution Document" document
    Then the response status code should be 403
    When I go to the edit form of the "Solution Discussion" discussion
    Then the response status code should be 403
    When I go to the edit form of the "Solution Custom page 1" "custom page"
    Then the response status code should be 403
    When I go to the edit form of the "Solution Custom page 2" "custom page"
    Then the response status code should be 403
    When I go to the edit form of the "Solution Custom page 3" "custom page"
    Then the response status code should be 403
    When I go to the edit form of the "Solution Distribution" distribution
    Then the response status code should be 403
    When I go to the edit form of the "Solution Release 1" release
    Then the response status code should be 403
    When I go to the edit form of the "Solution Release 2" release
    Then the response status code should be 403
    When I go to the edit form of the "Solution Release 3" release
    Then the response status code should be 403

    Examples:
      | User               |
      | John Moderator     |
      | Anne Group Admin   |
      | Andrew Facilitator |
