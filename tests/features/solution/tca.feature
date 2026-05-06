@api @group-i @javascript
Feature: Solution TCA agreement
  In order to ensure activity by facilitators
  As a site owner and/or a collection owner
  I want users to sign the TCA agreement before creating a solution.

  Scenario: Authenticated users can access the solution TCA agreement page.
    Given the following "collection" content:
      | title             |
      | Agreed collection |

    When I am logged in as a facilitator of the "Agreed collection" collection
    And I go to the "Agreed collection" collection
    And I click "Add solution" in the plus button menu

    When I fill in the following:
      | Title    | Solution A      |
      | Abstract | Abstract of "A" |
    And I enter "Yaks and goats are friendly pets." in the "Description" wysiwyg editor
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
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
    And I click "Additional information"
    And I select "Logging Service" options from "Solution type"
    And I press "Confirm"
    And I click "Terms of Agreement"

    Then a modal should open
    And I should see the text "Terms of agreement" in the "Modal title"
    And I should see the text "The eligibility criteria of Interoperable Europe Portal's interoperability solutions have been redefined." in the "Modal content" region

  Scenario: Users do not see TCA agreement during editing.
    Given the following "solution" content:
      | title      |
      | Solution B |

    When I am logged in as a facilitator of the "Solution B" solution
    And I go to the edit form of the "Solution B" solution
    And I press "Confirm"
    And I should not see the text "I have read and agreed"
