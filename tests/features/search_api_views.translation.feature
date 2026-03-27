@api @group-j
Feature: Search API views show translated content with english as fallback

  Background:
    Given collection content:
      | title           |
      | Test collection |
    And solution multilingual content:
      | title               | collection      | abstract            | langcode | default_translation  |
      | English Solution    | Test collection | Only in English     | en       | 1                    |
      | Bilingual Solution  | Test collection | Original English    | en       | 1                    |
      | BG Solution         | ~~~             | BG Translation      | bg       | 0                    |
    And custom_page multilingual content:
      | title            | collection      | langcode | default_translation  |
      | Test custom page | Test collection | en       | 1                    |
      | BG custom page   | ~~~             | bg       | 0                    |
    And oss_solution multilingual content:
      | title               | status    | oss_source        | langcode | default_translation |
      | Test oss catalogue  | published | developers_italia | en       | 1                   |
      | BG oss catalogue    | ~~~       | ~~~               | bg       | 0                   |

  Scenario: Test "oss_solutions_listing" view shows translated content
    And I append "block" to "field_paragraphs_body" field in "node" entity with following "Test custom page" title:
      | paragraph_block | views_block:oss_solutions_listing-most_active |
    And I append "block" to "field_paragraphs_body" field in "node" entity with following "Test custom page" title:
      | paragraph_block | views_block:oss_solutions_listing-most_recent |
    When I visit the "Test custom page" "custom page"
    Then I should see "Test oss catalogue"
    When I click "Bulgarian"
    Then I should see "BG oss catalogue"

  Scenario: Test "recommended_solutions" view shows translated content
    Given I am logged in as a user with the "interoperability_validator" role
    Then I set interoperable flag to "Bilingual Solution" solution
    And I append "block" to "field_paragraphs_body" field in "node" entity with following "Test custom page" title:
      | paragraph_block | views_block:recommended_solutions-recommended |
    When I visit the "Test custom page" "custom page"
    Then I should see "Bilingual Solution"
    When I click "Bulgarian"
    Then I should see "BG Solution"

  Scenario: Test "search_oss_catalogue" view shows translated content
    Given I visit the OSS search page
    Then I should see "Test oss catalogue"
    When I click "Bulgarian"
    Then I should see "BG oss catalogue"

  Scenario: Test "solutions" view shows translated content
    When I visit the solution overview page
    Then I should see the following lines of text:
      | English Solution    |
      | Bilingual Solution  |
    And I should not see "BG Solution"
    When I click "Bulgarian"
    Then I should see the following lines of text:
      | English Solution    |
      | BG Solution         |
    And I should not see "Bilingual Solution"
