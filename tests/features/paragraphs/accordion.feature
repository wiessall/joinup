@api @javascript @group-h
Feature:
  As a site builder of the site
  I need to be able to use "Accordion" paragraph for content.

  Background:
    Given the following "collection" content:
      | title                 | topic |
      | Paragraphs collection | HR    |

  Scenario Outline: Add an accordion to the custom page.
    Given I am logged in as a <role>
    And I go to the "Paragraphs collection" collection
    And I open the plus button menu
    And I click "Add custom page"
    And I fill in "Title" with "Paragraphs accordion page"
    And I fill in "Abstract" with "Abstract paragraphs accordion page"
    And I select "Demography" options from "Topic"

    Given I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    Then I click "Accordion"
    And press "Add Accordion item"

    Given I fill in the 1st "Item label" with "Super" in the "Accordion item" field
    And I enter "Clumsy text" in the 1st "Body" wysiwyg editor in the "Accordion item" field
    And I fill in the 2nd "Item label" with "Duper" in the "Accordion item" field
    And I enter "Crunchy text" in the 2nd "Body" wysiwyg editor in the "Accordion item" field
    And I press "Save" in the "Modal buttons" region
    And I press "Publish"
    And I see h1 span element with title "Paragraphs accordion page"
    And I should see the text "Super"
    And I should see the text "Duper"
    But I should not see the text "Clumsy text"
    And I should not see the text "Crunchy text"

    Given I am not logged in
    And I go to the "Paragraphs accordion page" custom_page

    And I press "Duper"
    # Wait for the animation to finish.
    And I wait for CSS animations to finish
    Then I should see the text "Crunchy text"
    But I should not see the text "Clumsy text"

    And I press "Super"
    # Wait for the animation to finish.
    And I wait for CSS animations to finish
    Then I should see the text "Clumsy text"
    But I should not see the text "Crunchy text"

    Examples:
      | role                                                  |
      | moderator                                             |
      | facilitator of the "Paragraphs collection" collection |

  Scenario: User should be able to click on "Complete drag & drop".
    # Regression test for ticket #ISAICP-8233.

    Given the following "custom_page" content:
      | title             | collection            | abstract             |
      | Nested paragraphs | Paragraphs collection | Nested page abstract |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Nested paragraphs" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a moderator
    And I go to the edit form of the "Nested paragraphs" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Accordion"
    And press "Add Accordion item"

    Then I fill in the 1st "Item label" with "Super" in the "Accordion item" field
    And I enter "Clumsy text" in the 1st "Body" wysiwyg editor in the "Accordion item" field
    And I click "Toggle Actions" in the "Accordion item" paragraphs field
    And I press "Drag & drop"
    And I press "Complete drag & drop"

    When the following fields should be present "Item label, Body"
