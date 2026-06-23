@api @javascript @group-g @loggedErrors
Feature: Translating a solution should not cause field group errors.
  In order to make solutions available in multiple languages
  As a translator
  I need to be able to translate solution nodes without encountering errors

  Scenario: Translating a solution does not trigger PHP warnings from field groups.
    Given the following "solution" content:
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

  Scenario: Language is presented in the interface language.
    Given "fr" translation for the "taxonomy_term" entity with label "English":
      | field | value   |
      | name  | Anglais |
    And "fr" translation for the "taxonomy_term" entity with label "Belgium":
      | field | value    |
      | name  | Belgique |
    When I am logged in as a moderator
    # French version of the "Portal Support" collection.
    And I go to "/fr/node/3"
    And I open the plus button menu
    And I click "Add solution"
    Then the url should match "/fr/node/3/solution/add"
    Then the "Language availability" tagify should contain the following option "Anglais"
    When I click "Contact details"
    And I press "Add new owner"
    And I wait for AJAX to finish
    Then the "Country" tagify should contain the following option "Belgique"
