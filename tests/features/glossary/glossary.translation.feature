@api @group-j
Feature: Translating glossary

  Background:
    Given collection content:
      | title                    | abstract                                       |
      | Collection With Glossary | This is a simple test for glossary translation |
    And glossary content:
      | title  | synonyms | summary      | definition                  | collection               |
      | Simple | Easy     | Some summary | Long, long definition field | Collection With Glossary |

  Scenario: Test creating node translation from UI.
    Given I am logged in as a user with the "moderator" role
    # Assert language select options/value.
    When I go to the edit form of the "Simple" glossary
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"

    # Create translation for glossary
    When I go to the homepage of the "Simple" glossary
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    And the following fields should be present "Glossary term nam, Synonym, Definition"
    When I fill in "Glossary term nam" with "Prosty"
    And I fill in "Definition" with "Definicja tłumaczenia"
    And I fill in "Synonym" with "Łatwy"
    When I press "Save (all translations)"

    # Create translation for collection
    When I go to the homepage of the "Collection With Glossary" collection
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    When I fill in "Title" with "Kolekcja ze słownikiem"
    And I fill in "Abstract" with "To prosty test tłumaczenia słownika"
    And I press "Save (all translations)"
    # Check if the glossary was properly processed in collection_glossary
    And I should see the link "prosty"
    Then I click "prosty"
    Then I should see the following lines of text:
      | Prosty                |
      | Definicja tłumaczenia |
      | Prosty                |

    # Delete glossary translation
    When I go to the homepage of the "Simple" glossary
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Prosty?"
    When I press "Delete Polish translation"
    Then I should see the success message "Polish translation of the Glossary term Prosty has been deleted."
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row
    # Check if the glossary was properly processed in collection_glossary
    When I go to the homepage of the "Collection With Glossary" collection
    And I click "Translate" in the "Entity actions" region
    And I click "Kolekcja ze słownikiem"
    Then I should not see the link "prosty"

  Scenario: Users who do not have edit access cannot manage translations.
    Given I am logged in as an authenticated
    When I visit the translation overview form of the "Simple" glossary
    Then the response status code should be 403
