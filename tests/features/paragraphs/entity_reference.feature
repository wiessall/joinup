@api @group-h
Feature:
  As a site builder of the site
  I need to be able to use "entity_reference" paragraphs for content.

  Background:
    Given the following collection:
      | title | Paragraphs collection |
      | state | published             |

  @javascript
  Scenario: Entity reference displays related content.
    Given custom_page content:
      | title                 | collection            | state     | abstract              |
      | Entity reference page | Paragraphs collection | published | Entity page abstract  |
      | Related content       | Paragraphs collection | published | Related page abstract |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Entity reference page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "Entity reference page" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Entity reference"
    When I type "Related conte" in the "Content" autocomplete field
    And I wait until the page contains the text "Related content"
    And I pick "Related content" from the autocomplete suggestions
    When I press "Save" in the "Modal buttons" region
    And I press "Update"

    When I am an anonymous user
    And I visit the "Entity reference page" custom page
    Then I should see the "Related content" tile
