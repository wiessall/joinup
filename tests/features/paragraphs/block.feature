@api @group-h
Feature: Block paragraph tests.

  @javascript
  Scenario: Recommended solutions
    Given collection content:
      | title            | state     |
      | Block collection | published |
    Given solution content:
      | title        | collection       | state     |
      | Early Omega  | Block collection | published |
      | Snake Timely | Block collection | published |
    And custom_page content:
      | title                 | collection       | state     |
      | Recommended solutions | Block collection | published |
    And I append "block" to "field_paragraphs_body" field in "node" entity with following "Recommended solutions" title:
      | paragraph_block | views_block:recommended_solutions-recommended |

    Given I am logged in as a user with the "interoperability_validator" role
    When I go to the homepage of the "Early Omega" solution
    And I click "Set interoperable flag" in the "Header" region

    When I am an anonymous user
    And I visit the "Recommended solutions" custom page
    Then I should see the "Early Omega" tile
    But I should not see the "Snake Timely" tile
