@api @group-e
Feature:
  In order to manage the EIF perspective
  as a user of the website
  I need to be able to access a page where I can configure them.

  Background:
    Given solutions:
      | title             | state     |
      | Some EIF solution | published |

  Scenario Outline: Only moderators can access the EIF perspective page.
    Given I am <role>
    And I go to the "Some EIF solution" solution
    Then I should <see or not see> the link "EIF perspective"

    Examples:
      | role                                                           | see or not see |
      | not logged in                                                  | not see        |
      | logged in as a user with the authenticated role                | not see        |
      | logged in as a facilitator of the "Some EIF solution" solution | not see        |
      | logged in as a user with the moderator role                    | see            |

  Scenario: The authorized user should be able to add edit all available fields.
    Given I am logged in as a user with the moderator role
    And I go to the "Some EIF solution" solution
    And I click "EIF perspective"
    Then I should see the heading "Some EIF solution EIF perspective"
    And the following fields should be present "Description, Type of solution"
    And the following field widgets should be present "Principle, Layer, Model"
    And I should see "Recommendation"

    When I press "Save"
    Then I should see the following error messages:
      | error messages                                                                                    |
      | Type of solution field is required.                                                               |
      | An EIF perspective cannot be created without any information. Please, fill in at least one field. |

    Given I fill in "Description" with "It will be an amazing principle."
    # Solution type.
    And I check "Assessment tools"
    And I check "Common frameworks"
    # Principle.
    And I check "Subsidiarity and proportionality"
    And I check "Openness"
    # Model.
    And I check "Coordination function"

    # Test the '_none' option label, the better naming of the recommendations
    # and the order - that 10 does not come after 1 (string comparison) and 2
    # follows normally.
    Then the "Recommendation" select should contain the following options:
      | - Select a Recommendation -                          |
      | Recommendation 1 \| Subsidiarity and proportionality |
      | Recommendation 2 \| Openness                         |

    Given I select "Recommendation 2 | Openness" from "Recommendation"
    And I press "Save"
    Then I should see the heading "Some EIF solution"
