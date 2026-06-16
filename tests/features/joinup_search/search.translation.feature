@api @group-j
Feature: Test global search in multilingual context

  Scenario: The custom page title included in collection search uses the current language
    Given the following "collection" content:
      | title        | state     |
      | Colour wheel | published |
    And the following "collection" entity translations:
      | source       | language | title             |
      | Colour wheel | fr       | Roue des couleurs |
    And the following "custom_page" content:
      | title         | collection   | state     |
      | Colour theory | Colour wheel | published |
    And the following "custom page" entity translations:
      | source        | language | title                |
      | Colour theory | fr       | Théorie des couleurs |
    And the following custom page navigation menu structure for the "Colour wheel" collection:
      | title         | enabled |
      | Colour theory | yes     |
    When I visit "/fr/search?keys=Théorie des couleurs"
    Then I should see the "Roue des couleurs" tile

  Scenario: The distribution title indexed for solution search uses the current language
    Given the following "solution" content:
      | title      | state     |
      | Stellar db | published |
    And the following "solution" entity translations:
      | source     | language | title          |
      | Stellar db | fr       | Base stellaire |
    And the following "distribution" content:
      | title           | solution   |
      | Cartography pkg | Stellar db |
    And the following "distribution" entity translations:
      | source          | language | title               |
      | Cartography pkg | fr       | Paquet géographique |
    When I visit "/fr/search?keys=Paquet géographique"
    Then I should see the "Base stellaire" tile

  Scenario: The contact label indexed for release search uses the current language
    Given the following "contact_information" content:
      | name          | email                |
      | Helpdesk Unit | helpdesk@example.com |
    And the following "contact_information" entity translations:
      | source        | language | title               |
      | Helpdesk Unit | fr       | Assistance centrale |
    And the following "solution" content:
      | title       | state     | contact information |
      | Genome base | published | Helpdesk Unit       |
    And the following "solution" entity translations:
      | source      | language | title          |
      | Genome base | fr       | Base génomique |
    And the following "release" content:
      | title          | solution    | state     |
      | Genome base v1 | Genome base | published |
    And the following "release" entity translations:
      | source         | language | title             |
      | Genome base v1 | fr       | Base génomique v1 |
    When I visit "/fr/search?keys=Assistance centrale"
    Then I should see the "Base génomique v1" tile

  Scenario: Topic badges on content tiles use the current language.
    Given the following term entity translations:
      | source     | language | name        |
      | Demography | fr       | Démographie |
    And the following "collection" content:
      | title           | topic      | state     |
      | Climate Project | Demography | published |
    When I visit "/fr/collections"
    Then I should see the text "Démographie" in the "Climate Project" tile
    But I should not see the text "Demography" in the "Climate Project" tile

  Scenario: The group label in the search bar badge uses the current language.
    Given the following "collection" content:
      | title             | state     |
      | Nordic collection | published |
    And the following "collection" entity translations:
      | source            | language | title               |
      | Nordic collection | fr       | Collection nordique |
    When I go to the "fr" version of the "Nordic collection" collection
    Then the page should show the chips:
      | Collection nordique |
