@api @group-h
Feature:
  As a site builder of the site
  I need to be able to use "image" paragraphs for content.

  Background:
    Given the following "collection" content:
      | title                 |
      | Paragraphs collection |

  @javascript
  Scenario: User should be able to add image paragraph.
    Given the following "custom_page" content:
      | title                | collection            | abstract            |
      | Image paragraph page | Paragraphs collection | Image page abstract |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Image paragraph page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "Image paragraph page" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Image"

    When I attach the file "logo.png" to "Image"
    And I fill in "Alternative text" with "european-union.europa.eu"
    And I fill in "URL" with "https://european-union.europa.eu" in the "Modal content" region
    And I press "Settings" in the "Modal content" region
    And I select "Right" from "Alignment" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"

    Then the page should contain the html text '<div class="paragraph--image d-flex col-gap-3 row-gap-3 justify-content-end paragraph">'
    And I should see the "a" element with the "target" attribute set to "_blank" in the "Content" region
    And I should see the "a" element with the "rel" attribute set to "noopener" in the "Content" region
