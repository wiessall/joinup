@api @group-f @javascript
Feature:
  As a site builder of the site
  I need to be able to use "Webform" paragraph for content.

  Background:
    Given the following collection:
      | title | Webform collection |
      | state | published          |
    And users:
      | Username   | E-mail                 | First name | Family name |
      | john.smith | john.smith@example.com | John       | Smith       |

  @uploadFiles:logo.png
  Scenario: Add webform paragraph.
    Given the following webforms:
      | id           | title        | elements                                           |
      | test_webform | Test webform | [{"#type": "textfield", "#title": "Test element"}] |
    Given custom_page content:
      | title           | collection         | state     |
      | Webform example | Webform collection | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Webform example" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "Webform example" "custom page"
    And I click "Choose component"
    And I click "Webform"
    And I select "Test webform" from "Webform" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the text "Test element"
