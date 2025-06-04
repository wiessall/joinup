@api @group-i
Feature: Solution editing.
  In order to manage solutions
  As a solution owner or solution facilitator
  I need to be able to edit solutions through UI.

  Background:
    Given users:
      | Username     | E-mail                   |
      | Yancy Burton | yancy.burton@example.com |
    And owner content:
      | name      | owner type |
      | Acme inc. | Company    |
    And collection content:
      | title              |
      | Collection example |
    # Assign facilitator role in order to allow creation of a solution.
    # In UAT this can be done by creating the collection through the UI
    # with the related user.
    And the following collection user memberships:
      | collection         | user         | roles       |
      | Collection example | Yancy Burton | facilitator |
    And solution content:
      | title            | logo     |
      | Another solution | logo.png |

  Scenario: A solution owner can edit only its own solutions.
    When I am logged in as "Yancy Burton"
    And I go to the homepage of the "Collection example" collection
    And I click "Add solution"
    Then I should see the heading "Add solution"
    When I fill in the following:
      | Title          | Solution A         |
      | Abstract       | Abstract of "A"    |
      | Description    | First letter       |
    And I select "Bulgarian" from "Language availability"
    And I select "Complete Solution" from "Solution classification"
    And I select "Defense" from "Domain"
    And I select "Demography" from "Topic"
    And I select "Logging Service" from "Solution type"
    And I press "Next"
    # Create owner.
    And I press "Add new" at the "Owner" field
    And I fill in "owner[form][0][title][0][value]" with "Yancy owner"
    And I check "Company"
    And I fill in "owner[form][0][country][0][target_id]" with "Belgium"
    And I press "Create owner"
    # Create contact information.
    And I fill in "contact[form][0][title][0][value]" with "Yancy Contact"
    And I fill in "contact[form][0][email][value]" with "yancyb@example.com"
    And I press "Next"
    And I select the radio button "Remote file URL"
    And I fill in "Remote URL" with "https://www.example.com"
    And I press "Add URL"
    And I jump to the "Confirmation" of "solution" wizard
    And I should see the link "https://www.example.com"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"
    # Then print last response
    Then I should see the heading "Solution A"

    And I should see the link "Edit"
    When I go to the edit form of the "Solution A" solution
    Then I should see the heading "Edit Solution Solution A"
    And the following fields should be present "Title, Abstract, Description, Solution type, Language availability"

    # This user is an owner only of Solution A.
    When I go to the homepage of the "Another solution" solution
    Then I should not see the link "Edit"
    When I go to the edit form of the "Another solution" solution
    Then I should get an access denied error

  Scenario: A solution facilitator can edit only the solutions he's associated with.
    Given collection content:
      | title              |
      | Collection example |
    Given solution content:
      | title      | collection         |
      | Solution B | Collection example |
    When I am logged in as a facilitator of the "Solution B" solution
    And I go to the homepage of the "Solution B" solution
    Then I should see the link "Edit"
    When I go to the edit form of the "Solution B" solution
    Then I should see the heading "Edit Solution Solution B"

    When I go to the homepage of the "Another solution" solution
    Then I should not see the link "Edit"
    When I go to the edit form of the "Another solution" solution
    Then I should get an access denied error

  @javascript
  Scenario: User can get more info about Solution classification
    When I am logged in as a facilitator of the "Another solution" solution
    And I go to the edit form of the "Another solution" solution
    And I click "More information about Solution classification"
    Then a modal should open
    And I should see the text "Solution classification" in the "Modal title"
    And I should see the text "Complete Solution" in the "Modal content" region
    And I should see the text "Artifact (product, tool, or digital service)." in the "Modal content" region

  @javascript
  Scenario: User should not see fields from other steps
    When I am logged in as a facilitator of the "Another solution" solution
    And I go to the edit form of the "Another solution" solution
    And the following fields should not be visible "Motivation"
