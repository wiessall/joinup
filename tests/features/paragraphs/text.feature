@api @grouped @group-f
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

  Scenario: Table is visible.
    Given custom_page content:
      | title              | collection            | state     |
      | Table page example | Paragraphs collection | published |
    And I append "text" to "field_paragraphs_body" field in "node" entity with following "Table page example" title:
      | field_body:value  | <table><tbody><tr><td>single table cell</td></tr></tbody></table> |
      | field_body:format | text_html                                                         |

    When I go to the "Table page example" custom page
    Then I should see an "td" element with the text "single table cell" in the "Content" region

  @javascript
  Scenario: Editor can add table.
    Given custom_page content:
      | title            | collection            | state     |
      | Table icon check | Paragraphs collection | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Table icon check" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "Table icon check" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Text"
    And the "Insert table" button should be available in the "Body" wysiwyg editor
    And I press the button "Insert table" in the "Body" wysiwyg editor
    And I press "Save" in the "Modal buttons" region
    And I press "Update"

  Scenario: Create a link with id only.
    Given custom_page content:
      | title       | collection            | state     |
      | Anchor link | Paragraphs collection | published |
    And I append "text" to "field_paragraphs_body" field in "node" entity with following "Anchor link" title:
      | field_body:value  | <a id="anchorlinkid"></a> |
      | field_body:format | text_html                                                         |

    When I go to the "Anchor link" custom page
    Then the response should contain "<a id=\"anchorlinkid\"></a>"
