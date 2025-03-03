@api @group-e
Feature:
  In order to not leave orphaned entities
  As a collection owner
  I need to be unable to delete groups that have group content.

  Scenario Outline: Delete a group with group content
    Given <group type> content:
      | title                       | state   |
      | <group label> with children | <state> |
    And news content:
      | title | state     |
      | News  | published |
    And event content:
      | title | state     |
      | Event | published |
    And document content:
      | title    | state     |
      | Document | published |
    And discussion content:
      | title      | state     |
      | Discussion | published |

    When I am logged in as a moderator
    And I go to the delete form of the "<group label> with children" <group type>
    And I press "Delete"
    Then I should see the text "The <group label> <group label> with children has been deleted."
    When I go to the "Discussion" discussion
    Then I should not see the heading "Discussion"

    Examples:
      | group label | group type |
      | Collection  | collection |
      | Solution    | solution   |
