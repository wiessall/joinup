@api @group-c @javascript
Feature: As a moderator of the site
  I want to be able to translate video paragraphs.

  Scenario: Videos are translatable.
    Given I am logged in as a moderator
    And I go to the "Portal Support" collection
    And I open the plus button menu
    And I click "Add custom page"
    And I fill in "Title" with "Video test page"
    And I fill in "Abstract" with "Short description"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout | one column |
    And I add a "Video" paragraph in the "Custom page body" form field:
      | Remote video URL | type: text - value: https://www.youtube.com/watch?v=dQw4w9WgXcQ |
    And I select "Demography" options from "Topic"
    And I press "Publish"
    Then I should see the heading "Video test page"

    When I open the header local tasks menu
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "French" row
    # Click the "Edit"/"Modifier" button to edit the video paragraph in the translation.
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I fill in "Remote video URL" with "https://www.youtube.com/watch?v=9bZkp7q19f0"
    And I press "Save" in the "Modal buttons" region
    And I press "Save (all translations)"

    When I go to the "Video test page" "custom page"
    Then the response should contain "dQw4w9WgXcQ"
    When I press "EN" in the "Top Bar" region
    When I click "French" in the "Language picker" region
    Then the response should contain "9bZkp7q19f0"
