@api @group-i
Feature: Solution TCA agreement
  In order to ensure activity by facilitators
  As a site owner and/or a collection owner
  I want users to sign the TCA agreement before creating a solution.

  @javascript
  Scenario: Authenticated users can access the solution TCA agreement page.
    Given collection content:
      | title             |
      | Agreed collection |

    When I am logged in as a facilitator of the "Agreed collection" collection
    And I go to the "Agreed collection" collection
    And I click "Add solution" in the plus button menu

    When I fill in the following:
      | Title    | Solution A      |
      | Abstract | Abstract of "A" |
    And I enter "Yaks and goats are friendly pets." in the "Description" wysiwyg editor
    And I select "Defense" from "Domain"
    And I select "Demography" from "Topic"
    And I select "Bulgarian" from "Language availability"
    And I select "Complete Solution" from "Solution classification"
    And I select "Defense" from "Domain"
    And I select "Demography" from "Topic"
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
    And I press "Create contact information"
    And I jump to the "Additional information" of "solution" wizard
    And I select "Logging Service" from "Solution type"
    And I jump to the "Confirmation" of "solution" wizard
    And I click "Terms of Agreement"

    Then a modal should open
    And I should see the text "Terms of agreement" in the "Modal title"
    And I should see the text "The eligibility criteria of Interoperable Europe Portal's interoperability solutions have been redefined." in the "Modal content" region

  Scenario: Users do not see TCA agreement during editing.
    Given solution content:
      | title      |
      | Solution B |

    When I am logged in as a facilitator of the "Solution B" solution
    And I go to the edit form of the "Solution B" solution
    And I jump to the "Confirmation" of "solution" wizard
    And I should not see "I have read and agreed"
