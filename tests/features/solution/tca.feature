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
    And I select "Flemish" from "Language Availabilty"
    And I select "Complete Solution" from "Solution classification"
    And I select "Logging Service" from "Solution type"
    And I press "Next"
    When I fill in the following:
      | owner[form][0][title][0][value]   | Yancy owner        |
      | contact[form][0][title][0][value] | Yancy Contct       |
      | E-mail address                    | yancyb@example.com |
    And I press "Create owner"
    And I press "Create contact information"
    And I jump to the "Confirmation" of "solution" wizard
    And I click "Terms of Agreement"

    Then a modal should open
    And I should see the text "Terms of agreement" in the "Modal title"
    And I should see the text "The eligibility criteria of Interoperable Europe Portal's interoperability solutions have been redefined." in the "Modal content" region
