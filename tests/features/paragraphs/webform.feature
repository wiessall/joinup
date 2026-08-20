@api @group-h
Feature:
  As a site builder of the site
  I need to be able to use "Webform" paragraph for content.

  Background:
    Given the following "collection" content:
      | title              |
      | Webform collection |
    And the following users:
      | Username   | E-mail                 | First name | Family name |
      | john.smith | john.smith@example.com | John       | Smith       |
    And the following webforms:
      | id           | title        | elements                                           |
      | test_webform | Test webform | [{"#type": "textfield", "#title": "Test element"}] |
    And the following "custom_page" content:
      | title           | collection         | state     | abstract                 |
      | Webform example | Webform collection | published | Webform example abstract |

  @javascript
  Scenario: Add webform paragraph.
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Webform example" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "Webform example" "custom page"
    And I add a component to section 1
    And a modal should open
    And I click "Webform"
    And I select "Test webform" from "Webform" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the text "Test element"

  Scenario: Webform paragraphs don't make pages uncacheable
    Given I append "webform" to "field_paragraphs_body" field in "node" entity with following "Webform example" title:
      | paragraph_webform:target_id | test_webform |
      | paragraph_webform:status    | open         |

    When I am logged in as an "authenticated user"
    # Second page load must be a dynamic page cache HIT.
    And I go to the homepage of the "Webform example" "custom page"
    And I go to the homepage of the "Webform example" "custom page"
    And the page should be cached
