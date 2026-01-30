@api @javascript @group-g @loggedErrors
Feature: Translating a solution should not cause field group errors.
  In order to make solutions available in multiple languages
  As a translator
  I need to be able to translate solution nodes without encountering errors

  Scenario: Translating a solution does not trigger PHP warnings from field groups.
    Given there are no preexisting log records on the site

    When I am logged in as a developer
    And I go to "/collection/portal-support/solution/add"
    Then I should see the heading "Add solution"
    And I fill in "Title" with "Translation Test Solution"
    And I fill in "Abstract" with "Translation Test Solution testing"
    And I enter "This is a test text" in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    # Click the button to select an existing owner.
    And I press "Add new owner"
    And I fill in "Name" with "Organisation valid"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    # Create contact information.
    And I press "Create contact information"
    And I fill in "contact[form][0][title][0][value]" with "Ernst Rice"
    And I fill in "contact[form][0][email][value]" with "ernsy1999@test.com"
    And I press "Create contact information"
    And I click "Additional information"
    And I select "Data Owner" options from "Solution type"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Publish"

    #Translate.
    And I open the header local tasks menu
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "French" row

    And I fill in "Title" with "Solution de test de traduction"
    And I fill in "Abstract" with "Résumé court"
    And I press "Confirm"

    Then the site should not have log records with level lower than notice
