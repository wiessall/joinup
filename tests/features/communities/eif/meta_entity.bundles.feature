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

  Scenario: The EIF perspective page shows the solution label in the current language.
    Given the following "solution" entity translations:
      | source            | language | title             |
      | Some EIF solution | fr       | Portail EIF       |
    And the following EIF perspective data for the "Some EIF solution" solution:
      | Description | An interoperability solution. |
    When I go to the EIF perspective page of the "Some EIF solution" solution
    And I switch to the "fr" language
    Then I should see the text "The Portail EIF implements the following:"
