@api @group-i
Feature: Topic terms are redirected to search page.

  Background:
    Given "topic" terms:
      | uri                                 | tid  | name     |
      | http://joinup.eu/ontology/topic#foo | 5000 | Foo term |
    And collection content:
      | title          | state     | topic    |
      | Bar collection | published | Foo term |

  Scenario Outline: Topic terms are redirected to search page.
    Given I go to "<inbound url>"
    Then the relative url should be "<expected url>"
    And I should see the following facet summary "Foo term"

    Examples:
      | inbound url                                               | expected url                  |
      | /taxonomy/term/http_e_f_fjoinup_ceu_fontology_ftopic_hfoo | /search?f%5B0%5D=topic%3A5000 |
      | /taxonomy/term/5000                                       | /search?f%5B0%5D=topic%3A5000 |
