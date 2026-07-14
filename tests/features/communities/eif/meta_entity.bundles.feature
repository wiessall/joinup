@api @group-a
Feature:
  In order to manage the EIF perspective
  as a user of the website
  I need to be able to access a page where I can configure them.

  Background:
    Given the following "solution" content:
      | title             |
      | Some EIF solution |

  Scenario Outline: Only moderators can access the EIF perspective page.
    Given I am <role>
    And I go to the "Some EIF solution" solution
    Then I should <see or not see> the link "EIF perspective"

    Examples:
      | role                                                           | see or not see |
      | not logged in                                                  | not see        |
      | logged in as a user with the authenticated role                | not see        |
      | logged in as a facilitator of the "Some EIF solution" solution | not see        |
      | logged in as a owner of the "Some EIF solution" solution       | not see        |
      | logged in as a user with the moderator role                    | see            |

  Scenario: EIF perspective link is not visible on an archived solution
    Given the workflow state of the "Some EIF solution" "content" is changed to "archived"
    And I am logged in as a user with the moderator role
    When I go to the "Some EIF solution" solution
    Then I should not see the link "EIF perspective" in the "Entity actions" region
