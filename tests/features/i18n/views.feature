@api @group-g
Feature: Views display translated entities correctly.
  In order to browse content in the user's language
  As a site visitor
  I need view pages and blocks to load content appropriately.

  # covers solution_classification view.
  Scenario: Solution classification terms are listed on the public classification page.
    Given the following locale string translations:
      | source            | language | translation       | status |
      | Complete Solution | fr       | Solution complète | 1      |
    When I am an anonymous user
    And I visit "/solution-classification"
    Then I should see "Complete Solution"
    When I visit "/fr/solution-classification"
    Then I should see "Solution complète"

  # covers custom_pages view.
  Scenario: EU language terms and custom pages are prepared for entity reference views.
    Given collection content:
      | title          |
      | Views Test Hub |
    And solution content:
      | title               | collection     |
      | Views Test Solution | Views Test Hub |
    And custom_page content:
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
    Given collection content:
      | title             | state     |
      | A World of Things | published |
    And glossary content:
      | title    | synonyms | summary             | definition                       | collection        | state     |
      | Alphabet | ABC      | Summary of Alphabet | Long, long body definition field | A World of Things | published |
    And the following glossary entity translations:
      | source   | language | title     | definition           | summary            |
      | Alphabet | fr       | Alphabète | Définition détaillée | Résumé de l'entrée |
    When I am an anonymous user
    And I go to the "A World of Things" collection
    And I click "Glossary"
    Then I should see the link "Alphabet"
    And I go to the "fr" version of the "A World of Things" collection
    And I click "Glossary"
    Then I should see "Alphabète"

  # covers assessment_reports view.
  @demoAssessment
  Scenario: Assessment report nodes are prepared for the assessment reports block view.
    Given collection content:
      | title          |
      | Assessment Hub |
    And assessment_report content:
      | title         | state     | organization         | collection     |
      | Annual Report | published | default organization | Assessment Hub |
    And assessment_report content:
      | title          | state     | organization         | collection     | langcode |
      | Rapport annuel | published | default organization | Assessment Hub | fr       |
    And the following locale string translations:
      | source         | language | translation     | status |
      | Assessment Hub | fr       | Pôle évaluation | 1      |
      | Annual Report  | fr       | Rapport annuel  | 1      |
    When I am an anonymous user
    And I go to the "Interoperability assessments space" collection
    And I click "Assessment reports"
    Then I should see "Annual Report"
    When I go to the "fr" version of the "Interoperability assessments space" collection
    And I click "Assessment reports"
    Then I should see "Rapport annuel"

  # covers latest_news view.
  # covers in_the_spotlight view.
  Scenario: News content is prepared for the latest news and in the spotlight blocks.
    Given collection content:
      | title        |
      | News Central |
    And news content:
      | title          | headline       | collection   | state     |
      | Breaking Story | Latest updates | News Central | published |
    And the following news entity translations:
      | source         | language | title             |
      | Breaking Story | el       | Breaking GR Story |
    And the "In the spotlight" content listing contains:
      | type    | label          |
      | content | Breaking Story |
    When I am on the homepage
    Then I should see "Breaking Story"
    When I visit "/el"
    Then I should see "Breaking GR Story"

  # covers eu_oss_catalogue_admin view.
  Scenario: OSS solutions are accessible on the public OSS catalogue page.
    Given oss_solution content:
      | title      | oss_development_status | oss_source        | oss_categories | oss_short_description  | status    | oss_release_date |
      | OpenAccess | development            | developers_italia | data-analytics | Open access initiative | published | 2023-06-01       |
    And oss_solution content:
      | title            | oss_development_status | oss_source        | oss_categories | oss_short_description     | status    | oss_release_date | langcode |
      | Ανοικτή πρόσβαση | development            | developers_italia | data-analytics | Initiative d'accès ouvert | published | 2023-06-01       | el       |
    And the following locale string translations:
      | source                 | language | translation               | status |
      | OpenAccess             | fr       | OpenAccess                | 1      |
      | Open access initiative | fr       | Initiative d'accès ouvert | 1      |
    When I am an anonymous user
    And I visit "/oss-solutions"
    Then I should see "OpenAccess"
    When I visit "/el/oss-solutions"
    Then I should see "Ανοικτή πρόσβαση"
