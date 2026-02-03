@api @javascript @group-g @loggedErrors
Feature: Translating a solution should not cause field group errors.
  In order to make solutions available in multiple languages
  As a translator
  I need to be able to translate solution nodes without encountering errors

  Scenario: Translating a solution does not trigger PHP warnings from field groups.
    Given solution content:
      | title                     | state     |
      | Translation Test Solution | published |

    And there are no preexisting log records on the site

    When I am logged in as a developer
    Then I go to the "Translation Test Solution" solution

    #Translate.
    And I open the header local tasks menu
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "French" row

    And I fill in "Title" with "Solution de test de traduction"
    And I fill in "Abstract" with "Résumé court"
    And I press "Confirm"

    Then the site should not have log records with level lower than notice
