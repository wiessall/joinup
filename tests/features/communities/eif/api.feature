@api
Feature:
  Basic test steps for the EIF perspective meta entity.

  Scenario: Create an EIF perspective and view the data.
    Given the following solution:
      | title       | EIF solution                                     |
      | description | Some description not to be shown in the EIF page |
      | state       | validated                                        |
    Given the following EIF perspective data for the "EIF solution" solution:
      | Description      | This should be in the description field.                                                   |
      | Type of solution | Common frameworks                                                                          |
      | Principle        | name: Underlying Principle 1: Subsidiarity and proportionality, description: Hello world 1 |
      | Principle        | name: Underlying Principle 3: Transparency, description: Hello world 3                     |
      | Layer            | name: Interoperability Layer 3: Legal interoperability, description: Hello layer 3         |
      | Recommendation   | name: Recommendation 21, description: This is recommended                                  |
      | Recommendation   | name: Recommendation 15, description: This is also recommended                             |
    Then there should be an EIF perspective associated with the "EIF solution" solution

    When I go to the EIF perspective page of the "EIF solution" solution
    Then I should see the heading "Solution: EIF solution"
    And I should see the heading "General information"

    And I should see the text "This should be in the description field."
    But I should not see the text "Some description not to be shown in the EIF page"

    And I should see the text "Common frameworks"
    And the "Underlying Principle 1: Subsidiarity and proportionality" should have the "title" set to "Hello world 1"
    And the "Underlying Principle 3: Transparency" should have the "title" set to "Hello world 3"
    And the "Interoperability Layer 3: Legal interoperability" should have the "title" set to "Hello layer 3"

    And I should see the following lines of text:
      # Recommendation titles appear with their related term a
      # "Recommendation [identifier] | Term name".
      | Recommendation 21 \| Interoperability Layer 1: Interoperability governance |
      | This is recommended                                                        |
      | Recommendation 15                                                          |
      | Recommendation 15 \| Underlying Principle 8: Security and privacy          |
