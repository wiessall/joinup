@api @group-e @terms @javascript
Feature:
  As a site builder of the site
  I need to be able to use "Accordion" paragraph for content.

  Background:
    Given the following collection:
      | title | Paragraphs collection |
      | state | published             |
      | topic | HR                    |

  Scenario Outline: Add an accordion to the custom page.
    Given I am logged in as a <role>
    And I go to the "Paragraphs collection" collection
    And I open the plus button menu
    And I click "Add custom page"
    And I fill in "Title" with "Paragraphs accordion page"
    And I select "HR" from "Topic"

    Given I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    Then I click "Accordion"
    And press "Add Accordion item"
    And I wait for AJAX to finish

    Given I fill in the 1st "Item label" with "Super" in the "Accordion item" field
    And I enter "Clumsy text" in the 1st "Body" wysiwyg editor in the "Accordion item" field
    And I fill in the 2nd "Item label" with "Duper" in the "Accordion item" field
    And I enter "Crunchy text" in the 2nd "Body" wysiwyg editor in the "Accordion item" field
    And I press "Save" in the "Modal buttons" region
    And I press "Publish"
    Then I should see the heading "Paragraphs accordion page"
    And I should see the text "Super"
    And I should see the text "Duper"
    But I should not see the text "Clumsy text"
    And I should not see the text "Crunchy text"

    Given I am not logged in
    And I go to the "Paragraphs accordion page" custom page

    And I press "Duper"
    # Wait for the animation to finish.
    And I wait for 2 second
    Then I should see the text "Crunchy text"
    But I should not see the text "Clumsy text"

    And I press "Super"
    # Wait for the animation to finish.
    And I wait for 2 second
    Then I should see the text "Clumsy text"
    But I should not see the text "Crunchy text"

    Examples:
      | role                                                  |
      | moderator                                             |
      | facilitator of the "Paragraphs collection" collection |
