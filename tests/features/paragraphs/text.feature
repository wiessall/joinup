@api @terms @grouped
Feature:
  As a site builder of the site
  In order to be able to better control the structure of my content
  I need to be able to place paragraphs for content.

  Background:
    Given the following collection:
      | title | Paragraphs collection |
      | state | published             |
      | topic | HR                    |

  @javascript
  Scenario: Custom page content is trimmed properly.
    Given custom_page content:
      | title          | collection            | state     |
      | Popular quotes | Paragraphs collection | published |
    And I append "text" to "field_paragraphs_body" field in "node" entity with following "Popular Quotes" title:
      | field_body:value  |     That's one small step for a man, a giant leap for mankind. |
      | field_body:format | text_html                                                      |
    When I am an anonymous user
    And I am on the homepage
    And I enter "mankind" in the search bar and press enter
    # Regression test that text is trimmed properly.
    Then the response should contain ">That's one small step for a man, a giant leap for mankind.<"

