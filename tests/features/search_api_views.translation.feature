@api @group-j
Feature: Search API views show translated content with english as fallback

  Scenario: Test "solutions" view shows translated content
    Given collection content:
      | title           | abstract                        | description                    |
      | Test collection | short description of collection | long description of collection |
    And solution multilingual content:
      | title               | collection      | abstract            | langcode | default_translation  |
      | English Solution    | Test collection | Only in English     | en       | 1                    |
      | Bilingual Solution  | Test collection | Original English    | en       | 1                    |
      | BG Solution         | Test collection | BG Translation      | bg       | 0                    |

    When I visit the solution overview page
    Then I should see the following lines of text:
      | English Solution    |
      | Bilingual Solution  |
    And I should not see "BG Solution"
    When I visit "/bg/solutions"
    Then I should see the following lines of text:
      | English Solution    |
      | BG Solution         |
    And I should not see "Bilingual Solution"
