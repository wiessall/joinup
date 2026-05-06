@api @group-b
Feature:
  As a site builder of the site
  I need to be able to use "Table of Contents" paragraph for content.

  @javascript
  Scenario:  Add Table of Contents to the custom page.
    And the following "custom_page" content:
      | title                      | abstract                   |
      | Increase Your Productivity | Increase Your Productivity |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Increase Your Productivity" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Increase Your Productivity" title:
      | paragraph_heading | H2 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h2";}} |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Increase Your Productivity" title:
      | paragraph_heading | H3 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h3";}} |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Increase Your Productivity" title:
      | paragraph_heading | H4 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h4";}} |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Increase Your Productivity" title:
      | paragraph_heading | H5 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h5";}} |

    Given I am logged in as a moderator
    And I go to the edit form of the "Increase Your Productivity" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Table of Contents"

    And I should see the text "Settings" in the "Modal content" region
    And I fill in "Title" with "ToC paragraph" in the "Modal content" region
    And I press "Settings" in the "Modal content" region
    And I select "H3 heading" from "Max heading level" in the "Modal content" region

    And I press "Save" in the "Modal buttons" region
    And I press "Update"

    Then I should see the text "ToC paragraph"
    And I should see the link "H2 heading paragraph"
    And I should see the link "H3 heading paragraph"
    But I should not see the link "H4 heading paragraph"
    And I should not see the link "H5 heading paragraph"
