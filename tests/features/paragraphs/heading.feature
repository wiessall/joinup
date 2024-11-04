@api @group-h
Feature:
  As a site builder of the site
  I need to be able to use "heading" paragraphs for content.

  Background:
    Given the following collection:
      | title | Paragraphs collection |
      | state | published             |

  Scenario: Heading paragraph is displayed as heading element.
    Given custom_page content:
      | title                | collection            | state     | abstract              |
      | Heading example page | Paragraphs collection | published | Heading page abstract |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Heading example page" title:
      | paragraph_heading | H2 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h2";}} |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Heading example page" title:
      | paragraph_heading | H3 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h3";}} |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Heading example page" title:
      | paragraph_heading | H4 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h4";}} |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Heading example page" title:
      | paragraph_heading | H5 heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h5";}} |

    When I go to the "Heading example page" custom page
    Then I should see an "h2" element with the text "H2 heading paragraph" in the "Content" region
    And I should see an "h3" element with the text "H3 heading paragraph" in the "Content" region
    And I should see an "h4" element with the text "H4 heading paragraph" in the "Content" region
    And I should see an "h5" element with the text "H5 heading paragraph" in the "Content" region

  @javascript
  Scenario: User should be able to edit behaviour settings.
    Given custom_page content:
      | title                  | collection            | state     | abstract |
      | Behaviour example page | Paragraphs collection | published | N/A      |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Behaviour example page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "Behaviour example page" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Heading"
    And I should see the text "Settings" in the "Modal content" region

    Then I am logged in as a facilitator of the "Paragraphs collection" collection
    And I go to the edit form of the "Behaviour example page" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Heading"
    When I should see the text "Settings" in the "Modal content" region

    Then I fill in "Heading text" with "Heading example" in the "Modal content" region
    And I press "Settings" in the "Modal content" region
    And I select "Right" from "Alignment" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    When the page should contain the html text '<div class="paragraph--heading d-flex col-gap-3 row-gap-3 justify-content-end paragraph">'
