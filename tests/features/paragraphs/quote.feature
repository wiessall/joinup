@api @group-h
Feature: Quote paragraph tests.

  Background:
    Given collection content:
      | title                 | state     | topic |
      | Paragraphs collection | published | HR    |

  Scenario: View quote paragraph in the custom page.
    Given custom_page content:
      | title          | collection            | state     | abstract             |
      | Popular quotes | Paragraphs collection | published | quotes page abstract |
    And I append "quote" to "field_paragraphs_body" field in "node" entity with following "Popular Quotes" title:
      | paragraph_quote | That's one small step for a man, a giant leap for mankind. |

    When I am an anonymous user
    And I visit the "Popular quotes" custom page
    Then the page should contain the html text "That's one small step for a man, a giant leap for mankind."
