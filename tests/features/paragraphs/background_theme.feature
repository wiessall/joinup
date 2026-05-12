@api @group-h
Feature: Dark blue background accessibility

  @javascript
  Scenario: Test Dark blue.
    Given the following "custom_page" content:
      | title                | abstract | topic             | state     |
      | Dark blue background | N/A      | Supplier exchange | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Dark blue background" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:1:{s:16:"background_theme";s:9:"dark-blue";}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "Dark blue background" "custom page"

    Then I click "Choose component"
    And a modal should open
    And I click "Text"
    And I enter "Lorem Ipsum is simply dummy text of the printing and typesetting industry. <a href='https://interoperable-europe.ec.europa.eu/'>Lorem Ipsum</a> has been the industry's standard dummy text ever since the 1500s, " in the "Body" wysiwyg editor
    And I press "Save" in the "Modal buttons" region

    Then I click "Choose component"
    And a modal should open
    And I click "Heading"
    And I fill in "Heading text" with "Heading example" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"

    Then the page meets accessibility standards
