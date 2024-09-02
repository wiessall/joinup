@api @group-a
Feature:
  Basic test steps for the EIF perspective meta entity.

  Scenario: Create an EIF perspective and view the data.
    Given solution content:
      | title         | description                                      | state     |
      | EIF solution  | Some description not to be shown in the EIF page | published |
      | EIF only type | Some description not to be shown in the EIF page | published |
    Given solution content:
      | Description                              | Solution type     | Principle                                                                                      | Layer                                            | Recommendation                                            | Recommendation                                                 |
      | This should be in the description field. | Common frameworks | Underlying Principle 1: Subsidiarity and proportionality, Underlying Principle 3: Transparency | Interoperability Layer 3: Legal interoperability | name: Recommendation 21, description: This is recommended | name: Recommendation 15, description: This is also recommended |
    Given solution content:
      | Description                              | Solution type     |
      | This should be in the description field. | Common frameworks |

    # Verify that solution group menu contains correct links.
    When I go to the "EIF solution" solution
    Then I should see the following navigation menu items in the specified order:
      | text     |
      | Overview |
    And I should see the following quick links menu items in the specified order:
      | text            |
      | EIF Perspective |

    Then there should be an EIF perspective associated with the "EIF solution" solution
    And there should be an EIF perspective associated with the "EIF only type" solution

    When I go to the EIF perspective page of the "EIF solution" solution
    Then I should see the heading "EIF Perspective attributes of EIF solution"
    And I should see the heading "General information"

    And I should see the text "Description:"
    And I should see the text "This should be in the description field."
    But I should not see the text "Some description not to be shown in the EIF page"

    # Verify that only columns with data are shown.
    And I should see the heading "Principles"
    And I should see the heading "Interoperability layers"
    And I should see the text "implements the following EIF recommendations as explained below"
    But I should not see the heading "Conceptual model"

    And I should see the text "Common frameworks"
    And I should see the following links:
      | Subsidiarity and proportionality |
      | Transparency                     |
      | Legal interoperability           |

    And I should see the following lines of text in the correct order:
      # Recommendation titles appear with their related term a
      # "Recommendation [identifier] | Term name". The eif_recommendation terms
      # weight/order is respected.
      | Recommendation 15 \| Security and privacy        |
      | This is also recommended                         |
      | Recommendation 21 \| Interoperability governance |
      | This is recommended                              |

    When I go to the EIF perspective page of the "EIF only type" solution
    Then I should see the heading "EIF Perspective attributes of EIF only type"
    And I should see the heading "General information"

    And I should see the text "Description:"
    And I should see the text "This should be in the description field."
    But I should not see the text "Some description not to be shown in the EIF page"

    # Verify that only columns with data are shown.
    And I should not see the heading "Principles"
    And I should not see the heading "Interoperability layers"
    But I should not see the heading "Conceptual model"
    And I should not see the text "implements the following EIF recommendations as explained below"
