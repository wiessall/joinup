@api @group-i
Feature: Solution editing.
  In order to manage solutions
  As a solution owner or solution facilitator
  I need to be able to edit solutions through UI.

  Background:
    Given users:
      | Username     | E-mail                   |
      | Yancy Burton | yancy.burton@example.com |
    And the following "owner" content:
      | name      | owner type |
      | Acme inc. | Company    |
    And the following "collection" content:
      | title              |
      | Collection example |
    # Assign facilitator role in order to allow creation of a solution.
    # In UAT this can be done by creating the collection through the UI
    # with the related user.
    And the following collection user memberships:
      | collection         | user         | roles       |
      | Collection example | Yancy Burton | facilitator |
    And the following "solution" content:
      | title            | logo     |
      | Another solution | logo.png |

  @javascript
  Scenario: A solution owner can edit only its own solutions.
    When I am logged in as "Yancy Burton"
    And I go to the homepage of the "Collection example" collection
    And I click "Add solution" in the plus button menu
    Then I should see the heading "Add solution"
    # Tab "Solution information".
    When I fill in "Title" with "Solution A"
    And I fill in "Abstract" with "Abstract of A"
    And I enter "First letter" in the "Description" wysiwyg editor
    And I select "Bulgarian" options from "Language availability"
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    # Tab "Contact details".
    And I click "Contact details"
    # Create owner.
    And I press "Add new owner"
    And I fill in "Name" with "Yancy owner"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    # Create contact information.
    And I fill in "Name" with "Yancy Contact"
    And I fill in "E-mail address" with "yancyb@example.com"
    And I press "Create contact information"
    # Tab "Additional information".
    And I click "Additional information"
    And I select "Logging Service" options from "Solution type"
    # Check Confirmation.
    And I press "Confirm"
    Then I see the texts in the correct order:
      | Solution information                       |
      | Title: Solution A                          |
      | Abstract: Abstract of A                    |
      | Description: First letter                  |
      | Solution classification: Complete Solution |
      | Domain: Defense                            |
      | Topic: Demography                          |
      | Language availability: Bulgarian           |
      | Contact details                            |
      | Owner                                      |
      | Yancy owner                                |
      | Type: Company                              |
      | Country: Belgium                           |
      | Contact information                        |
      | Yancy Contact                              |
      | Additional information                     |
      | Status: Completed                          |
    And I check "I have read and agreed to the Terms of Agreement"
    And I should see the text "Current workflow state"
    And I press "Propose"
    Then I should see the heading "Solution A"

    When I open the header local tasks menu
    And I should see the link "Edit"
    When I go to the edit form of the "Solution A" solution
    Then I should see the heading "Edit Solution Solution A"
    And the following fields should be present "Title, Abstract, Description, Solution type, Language availability, Current workflow state"
    And the current workflow state should be "Proposed"
    And I press "Confirm"
    And I should see "Current workflow state: Proposed"

    # This user is an owner only of Solution A.
    When I go to the homepage of the "Another solution" solution
    Then I open the header local tasks menu
    And I should not see the link "Edit"
    When I go to the edit form of the "Another solution" solution
    Then I should see the heading "Access denied"

  Scenario: A solution facilitator can edit only the solutions he's associated with.
    Given the following "collection" content:
      | title              |
      | Collection example |
    Given the following "solution" content:
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
    And I should not see the text "Motivation"
    Then I press "Confirm"
    And the following fields should be visible "Motivation"
