@api @group-h @javascript
Feature:
  As a site builder of the site
  I need to be able to use "Tiles" paragraph for content.

  Background:
    Given the following "collection" content:
      | title                 | state     |
      | Paragraphs collection | published |
  Scenario: Add tiles paragraph to one column layout.
    Given the following "custom_page" content:
      | title                   | collection            | state     | abstract            |
      | Tiles paragraph example | Paragraphs collection | published | Tiles page abstract |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Tiles paragraph example" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a moderator
    And I go to the edit form of the "Tiles paragraph example" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Tiles"
    And I fill in "Title" with "Card title" in the "Modal content" region
    And I fill in "Description" with "This is some text within a card body." in the "Modal content" region
    And I fill in "Link" with "https://european-union.europa.eu" in the "Modal content" region
    And I attach the file "topic.png" to "Image"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"

    When I should see the heading "Card title"
    And I should see the text "This is some text within a card body."

  Scenario: Tiles paragraph is restricted.
    Given the following "custom_page" content:
      | title                   | collection            | state     |
      | Tiles paragraph example | Paragraphs collection | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Tiles paragraph example" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:21:"layout_twocol_section";s:6:"config";a:2:{s:5:"label";s:0:"";s:13:"column_widths";s:5:"50-50";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a moderator
    And I go to the edit form of the "Tiles paragraph example" "custom page"
    And I click the last "Choose component" link
    And a modal should open

    When I should not see the link "Tiles" in the "Modal content" region

  Scenario: Regression test - System not able to recognise the apostrophe on a text.
    Given the following "custom_page" content:
      | title                    | collection            | state     | abstract                       |
      | Tiles regression example | Paragraphs collection | published | Tiles regression page abstract |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Tiles regression example" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a moderator
    And I go to the edit form of the "Tiles regression example" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Tiles"
    And I fill in "Title" with "Board" in the "Modal content" region
    And I fill in "Description" with "Explore the roles and decisions of the Governance Board's." in the "Modal content" region
    And I fill in "Link" with "https://european-union.europa.eu" in the "Modal content" region
    And I attach the file "topic.png" to "Image"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"

    When I should see the heading "Board"
    And I should see the text "Explore the roles and decisions of the Governance Board's."
    And I should not see the text "Explore the roles and decisions of the Governance Board&#039;s."
