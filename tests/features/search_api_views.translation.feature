@api @group-j
Feature: Search API views show translated content with english as fallback

  Background:
    Given collection multilingual content:
      | title           | langcode  | default_translation  |
      | Test collection | en        | 1                    |
      | BG collection   | bg        | 0                    |
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

  Scenario: Test "solutions_meta_entity_block" view shows translated content
    Given custom_page multilingual content:
      | title     | collection      | langcode | default_translation  | nid    |
      | Test page | Test collection | en       | 1                    | 705372 |
      | BG page   | ~~~             | bg       | 0                    |        |
    And solution multilingual content:
      | title     | collection       | langcode | default_translation  |
      | Jordan 1  | Test collection  | en       | 1                    |
      | Jordan 2  | Test collection  | en       | 1                    |
      | Jordan BG | Test collection  | bg       | 0                    |
    And the following EIF perspective data for the "Jordan 1" solution:
      | Description | Desc 1 |
    And the following EIF perspective data for the "Jordan 2" solution:
      | Description | Desc 2 |
    Then I visit the "Test page" "custom page"
    And I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
    And I should not see "Jordan BG"
    When I click "Bulgarian"
    And I should see the following headings:
      | Jordan 1  |
      | Jordan BG |
    And I should not see "Jordan 2"

  Scenario: Test "group_feed" view shows translated content
    Given I go to the homepage of the "Test collection" collection
    When I click "RSS feed" in the "Entity actions" region
    Then I should see a valid RSS feed
    And the RSS feed items should be:
      | title                         |
      | Solution: English Solution    |
      | Solution: Bilingual Solution  |
      | Custom page: Test custom page |
    When I go to the homepage of the "Test collection" collection
    And I click "Bulgarian"
    And I click "RSS feed" in the "Entity actions" region
    Then I should see a valid RSS feed
    And the RSS feed items should be:
      | title                                 |
      # @todo Remove "English Solution" once the re-indexing fix is removed,
      # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-11121
      | Solution: English Solution  |
      | Solution: BG Solution                 |
      | Custom page: BG custom page           |

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

  Scenario: Test "content_overview" view shows translated content
    Given news multilingual content:
      | title            | collection      | langcode | default_translation  |
      | Test news        | Test collection | en       | 1                    |
      | Bilingual news   | Test collection | en       | 1                    |
      | BG news          | ~~~             | bg       | 0                    |
    Given I visit the content overview page
    Then I should see the following lines of text:
      | Test news       |
      | Bilingual news  |
    And I should not see "BG news"
    When I click "Bulgarian"
    Then I should see the following lines of text:
      | Test news    |
      | BG news      |
    And I should not see "Bilingual news"

  Scenario: Test "collections" view shows translated content
    Given I visit the collection overview page
    Then I should see the following lines of text:
      | Interoperability assessments space    |
      | Portal support                        |
      | Test collection                       |
    When I click "Bulgarian"
    Then I should see the following lines of text:
      | Interoperability assessments space    |
      | Portal support                        |
      | BG collection                         |

  Scenario: Test "search" view shows translated content
    Given I am on the advanced search page
    Then I should see the following lines of text:
      | Interoperability assessments space    |
      | Portal support                        |
      | Test collection                       |
    When I click "Bulgarian"
    Then I should see the following lines of text:
      | Interoperability assessments space    |
      | Portal support                        |
      | BG collection                         |
