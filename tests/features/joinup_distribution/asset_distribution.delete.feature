@api @group-e
Feature: Asset distribution deleting.
  In order to manage distributions
  As a solution owner or solution facilitator
  I need to be able to delete distributions through the UI.

  Scenario: "Delete" button should be shown to facilitators of the related solution.
    Given solution content:
      | title                 | description        | state     |
      | Rough valentine's day | Sample description | published |
    And users:
      | Username   | E-mail                |
      | Papa Roach | paparoach@example.com |
    And the following solution user memberships:
      | solution              | user       | roles                      |
      | Rough valentine's day | Papa roach | administrator, facilitator |
    And distribution content:
      | title            | description    | access url | solution              | state     |
      | Francesco's cats | Sample content | test.zip   | Rough valentine's day | published |

    When I am logged in as a facilitator of the "Rough valentine's day" solution
    And I go to the homepage of the "Francesco's cats" distribution
    And I click "Edit"
    Then I should see the link "Delete"
    When I click "Delete"
    And I press "Delete"
    Then the following email should have been sent:
      | recipient | Papa Roach                                                                                        |
      | subject   | Interoperable Europe Portal: A distribution has been deleted                                                           |
      | body      | The distribution Francesco's cats of the solution Rough valentine's day was successfully deleted. |
