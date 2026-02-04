@api @group-a
Feature: Creating nodes in foreign language

  Scenario: Create collection
    Given I am logged in as a developer
    When I go to the propose collection form
    Then I should see the heading "Propose collection"
    When I fill in the following:
      | Title       | Kolekcja               |
      | Abstract    | Krótki opis            |
      | Description | Długi opis             |
      | Name        | Overviewer contact     |
      | E-mail      | overviewer@example.com |
    And I select "Employment and Support Allowance" options from "Topic"
    And I attach the file "logo.png" to "Logo"
    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "ISA²"
    And I press "Add owner"
    And I fill in "Alternative text" with "Alternative logo text"
    And I select "Polish" from "Language"
    And I press "Publish"
    When I click "Translate" in the "Entity actions" region
    Then I should see "Polish (Original language)"
    And I should see "Kolekcja" in the "Polish (Original language)" row
    And I should see "Source" in the "Polish (Original language)" row

  @javascript
  Scenario: Create Solution
    Given I am logged in as a facilitator of the "Portal support" collection
    And I go to the homepage of the "Portal support" collection
    And I click "Add solution" in the plus button menu
    And I select "Polish" from "Language"
    When I fill in "Title" with "Solucja"
    And I fill in "Abstract" with "Short abstract"
    And I enter "Last letter" in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    And I press "Add new owner"
    When I fill in "Name" with "Organisation valid"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    And I fill in "Name" with "Contact Rapazoglou"
    And I fill in "E-mail address" with "contact_Rapazoglou@example.com"
    Then I press "Create contact information"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"

    Given I am logged in as a user with the "developer" role
    And  I go to the edit form of the "Solucja" solution
    And I press "Confirm"
    And I press "Publish"
    When I visit "/pl/node/3/solution/solucja/translations"
    And I should see "Solucja" in the "Polish (Original language)" row
    And I should see "Source" in the "Polish (Original language)" row
