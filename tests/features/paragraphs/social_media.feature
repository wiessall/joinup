@api @group-c
Feature:
  As a site builder of the site
  I need to be able to use "Social media" paragraphs for content.

  Background:
    Given the following collection:
      | title | Paragraphs collection |
      | state | published             |

  @javascript
  Scenario: Call to action(s) has alignment setting.
    Given custom_page content:
      | title             | collection            | state     |
      | Social media page | Paragraphs collection | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Social media page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a facilitator of the "Paragraphs collection" collection
    And I go to the edit form of the "Social media page" "custom page"
    And I click "Choose component"
    And I click "Social media"
    When the available options in the "Platform" select should be "- None -, Twitter, LinkedIn, Youtube, Mastodon, Facebook, Whatsapp, Vimeo, Instagram, GitHub"
    Then I select "Twitter" from "Platform" in the "Modal content" region
    And I wait for AJAX to finish
    And I fill in "Twitter" with "EU_Commission" in the "Modal content" region
    And I press "Settings" in the "Modal content" region
    And I select "Right" from "Alignment" in the "Modal content" region
    And I select "Light blue" from "Color" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
