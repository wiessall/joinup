@api @group-h
Feature:
  As a moderator of the site
  I need to be able to use "map" paragraphs for content.

  Background:
    Given the following collection:
      | title | Paragraphs collection |
      | state | published             |
      | topic | HR                    |

  @javascript
  Scenario: Moderator can manage map.
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
    And I click "Choose component"
    And a modal should open
    And I click "Map"
    # As the Webtools Map a webservice, we only test a fake JSON.
    And I fill in "JSON" with "{\"foo\":\"bar\"}"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the success message "Custom page Don't Mess with the Zohan has been updated."
    And the response should contain "{\"foo\":\"bar\"}"
