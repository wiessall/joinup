@api @group-clone
Feature: EIF Recommendation

  Scenario: Test that EIF recommendation term canonical page is not regressing.
    Given I go to the "Recommendation 4" eif_recommendation term page
    Then I should see the heading "Recommendation 4"
    And I should see the following lines of text in the correct order:
      | Give preference to open specifications, taking due account of the coverage of functional needs, maturity and market support and innovation. |
      | Related term                                                                                                                                |
      | Underlying Principle 2: Openness                                                                                                            |
    And I should see the link "Underlying Principle 2: Openness"
