@api @group-h
Feature:
  As a moderator of the site
  I need to be able to use "iframe" and "map" paragraphs for content.

  Background:
    Given collection content:
      | title                 | state     | topic |
      | Paragraphs collection | published | HR    |

  @javascript
  Scenario: Moderator can manage map and iframe paragraph.
    Given users:
      | Username |
      | Zohan    |
    And the following collection user membership:
      | collection            | user  |
      | Paragraphs collection | Zohan |
    And custom_page content:
      | title                     | body        | collection            | author | state     | topic |
      | Don't Mess with the Zohan | Wanna mess? | Paragraphs collection | Zohan  | published | HR    |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Don't Mess with the Zohan" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Given I am logged in as a moderator
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click the last "Choose component" link
    And a modal should open
    And I click "IFrame"
    Then I press "Save" in the "Modal buttons" region
    When I should see the error message "Iframe URL field is required."
    And I should see the error message "Iframe Title field is required."
    Then I fill in "Iframe URL" with "http://example.com"
    And I fill in "Iframe Title" with "example.com"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    # We only test that the <iframe .../> element is in page. Unfortunately, we
    # cannot do precision RegExp search in page source, so we only check for the
    # existence of the iframe and its URL.
    When I should see 1 "iframe" elements
    And the response should contain "src=\"http://example.com\""

    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Map"
    # As the Webtools Map a webservice, we only test a fake JSON.
    And I fill in "JSON" with "{\"foo\":\"bar\"}"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the success message "Custom page Don't Mess with the Zohan has been updated."
    And the response should contain "{\"foo\":\"bar\"}"
