@api
Feature:
  In order to manage the EIF settings
  as a user of the website
  I need to be able to access a page where I can configure them.

  Background:
    Given solutions:
      | title             | state     |
      | Some EIF solution | validated |

  Scenario Outline: Only moderators can access the EIF settings page.
    Given I am <role>
    And I go to the "Some EIF solution" solution
    Then I should <see or not see> the link "EIF settings"

    Examples:
      | role                                                           | see or not see |
      | not logged in                                                  | not see        |
      | logged in as a user with the authenticated role                | not see        |
      | logged in as a facilitator of the "Some EIF solution" solution | not see        |
      | logged in as a user with the moderator role                    | see            |

  Scenario: The authorized user should be able to add edit all available fields.
    Given I am logged in as a user with the moderator role
    And I go to the "Some EIF solution" solution
    And I click "EIF settings"
    Then I should see the heading "Some EIF solution EIF settings"
    And the following fields should be present "Description, Type"
    And the following field widgets should be present "Principle, Layer, Model, Recommendation"

    Given I fill in "Description" with "It will be an amazing principle."
    And I select "Test resource" from "Type"
    And I select "Underlying Principle 2: Openness" from "EIF qualifier"
    And I press "Create qualifier"
    Then I should see the text "Underlying Principle 2: Openness"

    When I press "Save"
    Then I should see the heading "Some EIF solution"
    When I click "EIF settings"
    # The only "Edit" button is in the inline entity form for principles.
    And I press "Edit" in the "Content" region
    Then the option with text "Underlying Principle 2: Openness" from select "EIF qualifier" should be selected
    And I should see the text "It will be an amazing principle"
