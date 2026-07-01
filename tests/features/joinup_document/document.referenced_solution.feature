@api @group-e @javascript
Feature: "Referenced solution(s)" field for documents of type "Solution use cases".

  Scenario: The referenced solutions field toggles, filters, is required and keeps archived references.
    Given the following "collection" content:
      | title               | logo     |
      | Use Case Collection | logo.png |
    And the following "solution" content:
      | title              | collection          | logo     | state     |
      | Published Showcase | Use Case Collection | logo.png | published |
      | Archived Showcase  | Use Case Collection | logo.png | archived  |
    And the following "document" content:
      | title       | collection          | document type      | referenced solution | body                    | state |
      | Legacy Case | Use Case Collection | solution_use_cases | Published Showcase  | A sample use case body. | draft |
    And I am logged in as a facilitator of the "Use Case Collection" collection

    When I go to the homepage of the "Use Case Collection" collection
    And I click "Add document" in the plus button menu
    Then I should see the heading "Add document"
    And the following fields should not be visible "Referenced solution(s)"

    When I select "Solution use cases" from "Type"
    Then the following fields should be visible "Referenced solution(s)"

    # The required check must run before autocomplete commits a tag to the field.
    When I fill in the following:
      | Title       | Incomplete case |
      | Short title | Incomplete case |
      | Abstract    | Incomplete case |
    And I press "Save as draft"
    Then I should see the inline form error "A document of type Solution use cases must reference at least one solution."

    And the following fields should be visible "Referenced solution(s)"
    And the "Referenced solution(s)" tagify should contain the following option "Published Showcase"
    And the "Referenced solution(s)" tagify should not contain the following option "Archived Showcase"

    When I select "Document" from "Type"
    Then the following fields should not be visible "Referenced solution(s)"

    When the workflow state of the "Published Showcase" solution is changed to "archived"
    And I go to the "canonical" form of the "Legacy Case" document
    Then I should see the link "Published Showcase"

    When I go to the "edit" form of the "Legacy Case" document
    Then the following fields should be visible "Referenced solution(s)"
    And I should see "Published Showcase"
    When I press "Save as draft"
    Then I should see the heading "Legacy Case"
    And I should see the link "Published Showcase"
