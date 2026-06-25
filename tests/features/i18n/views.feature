@api @group-g
Feature: Views display translated entities correctly.
  In order to browse content in the user's language
  As a site visitor
  I need view pages and blocks to load content appropriately.

  # covers solution_classification view.
  Scenario: Solution classification terms are listed on the public classification page.
    Given the following term entity translation:
      | source            | language | name                |
      | Complete Solution | el       | Πλήρης λύση         |
    When I am an anonymous user
    And I visit "/solution-classification"
    Then I should see the text "Complete Solution"
    When I visit "/el/solution-classification"
    Then I should see the text "Πλήρης λύση"

  # covers custom_pages view.
  Scenario: EU language terms and custom pages are prepared for entity reference views.
    Given the following "collection" content:
      | title          |
      | Views Test Hub |
    And the following "solution" content:
      | title               | collection     |
      | Views Test Solution | Views Test Hub |
    And the following "custom_page" content:
      | title                         | collection     | solution            | state     |
      | Collection page with EN title | Views Test Hub |                     | published |
      | Solution page with EN title   |                | Views Test Solution | published |
    And the following "custom page" entity translations:
      | source                        | language | title                         |
      | Collection page with EN title | el       | Collection page with EL title |
      | Solution page with EN title   | el       | Solution page with EL title   |
    When I am logged in as a moderator
    And I go to the "el" version of the "Views Test Hub" collection
    Then I click "Settings" in the "Entity actions" region
    And I select "Collection page with EL title" from "Landing page"
    When I go to the "el" version of the "Views Test Solution" solution
    And I click "Edit" in the "Entity actions" region

  # covers glossary view.
  Scenario: Glossary terms are translated and correctly listed.
    Given the following "collection" content:
      | title             | state     |
      | A World of Things | published |
    And the following "glossary" content:
      | title    | synonyms | summary             | definition                       | collection        |
      | Alphabet | ABC      | Summary of Alphabet | Long, long body definition field | A World of Things |
    And the following glossary entity translations:
      | source   | language | title     | field_glossary_definition | summary            |
      | Alphabet | fr       | Alphabète | Définition détaillée      | Résumé de l'entrée |
    When I am an anonymous user
    And I go to the "A World of Things" collection
    And I click "Glossary"
    Then I should see the link "Alphabet"
    And I go to the "fr" version of the "A World of Things" collection
    And I click "Glossary"
    Then I should see the text "Alphabète"

  Scenario: A collection content listing falls back to untranslated content and shows translated titles when available.
    Given the following "collection" content:
      | title        |
      | Fallback Hub |
    And the following "news" content:
      | title             | headline   | body      | topic | collection   |
      | Untranslated News | Just facts | News body | Info  | Fallback Hub |
      | Translated News   | Headlines  | News body | Info  | Fallback Hub |
    And the following news entity translations:
      | source          | language | title              |
      | Translated News | fr       | Actualité traduite |

    When I am an anonymous user
    And I go to the homepage of the "Fallback Hub" collection
    Then I should see the "Untranslated News" tile
    And I should see the "Translated News" tile
    But I should not see the "Actualité traduite" tile

    When I go to the "fr" version of the "Fallback Hub" collection
    Then I should see the "Untranslated News" tile
    And I should see the "Actualité traduite" tile
    But I should not see the "Translated News" tile

  # covers assessments_reports view.
  Scenario: Assessment reports page lists translated assessment entities and falls back to the original language.
    Given the assessments collection exists
    And public_organisation assessments:
      | name | organisation_type          |
      | ACME | european_union_institution |
    And report assessments:
      | name              | provider | state     |
      | Translated Report | ACME     | published |
      | English Only      | ACME     | published |
    And the following report entity translations:
      | source            | language | name            |
      | Translated Report | fr       | Rapport traduit |
    And the following config translations:
      | config                         | path                                  | language | translation           |
      | views.view.assessments_reports | display.default.display_options.title | fr       | Rapports d'évaluation |
    When I am an anonymous user
    And I visit "collection/assessments/report/repository"
    Then I should see the heading "Assessment reports"
    And I should see the text "Translated Report"
    And I should see the text "English Only"
    When I click "French"
    Then I should see the heading "Rapports d'évaluation"
    And I should see the text "Rapport traduit"
    # Reports without a translation fall back to the original language.
    And I should see the text "English Only"
    And I should not see the text "Translated Report"

  # covers latest_news view.
  # covers in_the_spotlight view.
  Scenario: News content is prepared for the latest news and in the spotlight blocks.
    Given the following "collection" content:
      | title        |
      | News Central |
    And the following "news" content:
      | title          | headline       | collection   | state     |
      | Breaking Story | Latest updates | News Central | published |
    And the following news entity translations:
      | source         | language | title             |
      | Breaking Story | el       | Breaking GR Story |
    And the "In the spotlight" content listing contains:
      | type    | label          |
      | content | Breaking Story |
    When I am on the homepage
    Then I should see the text "Breaking Story"
    When I visit "/el"
    Then I should see the text "Breaking GR Story"
