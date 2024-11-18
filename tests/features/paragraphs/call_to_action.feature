@api @group-h
Feature:
  As a site builder of the site
  I need to be able to use "Call to action(s)" paragraphs for content.

  Background:
    Given collection content:
      | title                 | state     |
      | Paragraphs collection | published |

  @javascript
  Scenario: Call to action(s) has alignment setting.
    Given custom_page content:
      | title               | collection            | state     | abstract          |
      | Call to action page | Paragraphs collection | published | CTA page abstract |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Call to action page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a moderator
    And I go to the edit form of the "Call to action page" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Call to action(s)"
    And I fill in "Action" with "https://european-union.europa.eu" in the "Modal content" region
    And I fill in "Link text" with "european-union.europa.eu" in the "Modal content" region
    And I press "Settings" in the "Modal content" region
    And I select "Right" from "Alignment" in the "Modal content" region
    And I select "Light blue" from "Color" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    When the page should contain the html text '<div class="paragraph--call-to-action d-flex col-gap-3 row-gap-3 justify-content-end flex-wrap paragraph">'
    When the page should contain the html text '<a href="https://european-union.europa.eu" class="btn btn-secondary">european-union.europa.eu</a>'

  @javascript
  Scenario: Call to action(s) has pill setting.
    Given custom_page content:
      | title                 | collection            | state     |
      | Pilled call to action | Paragraphs collection | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Pilled call to action" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a moderator
    And I go to the edit form of the "Pilled call to action" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Call to action(s)"
    And I fill in "Action" with "https://european-union.europa.eu" in the "Modal content" region
    And I fill in "Link text" with "europa.eu" in the "Modal content" region
    And I press "Settings" in the "Modal content" region
    And I check the box "Rounded corner"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    When the page should contain the html text '<a href="https://european-union.europa.eu" class="btn btn-primary rounded-pill">europa.eu</a>'
