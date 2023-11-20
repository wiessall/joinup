@api @group_e
Feature:
  As a site builder of the site
  I need to be able to use "Webform" paragraph for content.

  Background:
    Given the following collection:
      | title | Webform collection |
      | state | published          |

  @javascript
  Scenario: Add webform paragraph.
    Given custom_page content:
      | title           | collection         | state     |
      | Webform example | Webform collection | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Webform example" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a moderator
    And I go to the edit form of the "Webform example" "custom page"
    And I click "Choose component"
    And I click "Webform"
    And I select "Public Sector Tech Watch" from "Webform" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    When I should see the heading "Submitter information"
    And I should see the heading "Case Information"

    Then I log out
    And I go to the "Webform example" custom page
    When I should not see the heading "Submitter information"
    And I should see the text "Please login to access the form."

    Then I am logged in as a user with the "authenticated" role
    And I go to the "Webform example" custom page

    When I should see the heading "Submitter information"
    And the following fields should be visible "First name, Last name, Contact email, Submitter organisation"

    When I should see the heading "Case Information"
    And the following fields should be visible "Case title, Technology"
    And the following fields should be visible "Geographical extent, Functions of Government"
    And the following fields should be visible "Description, Is the technological solution available as Open Source?, Status"
    And the following fields should be visible "Additional info"

    When I should see the heading "Terms and Conditions"

    When I should see the heading "Send yourself a copy"
