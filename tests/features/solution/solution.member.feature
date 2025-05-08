@api @group-a
Feature: Content Creation Permissions
  As a group member,
  I want to have content creation permissions based on the solution's settings,
  So that I can add content when allowed.

  Scenario Outline: Content creation availability based on solution settings
    And solution content:
      | title               |
      | ResolveTech Systems |
    And the "content_creation" setting of the "ResolveTech Systems" solution is set to <content creation>
    Given I am logged in as a member of the "ResolveTech Systems" solution
    And I go to the "ResolveTech Systems" solution
    Then I <expected> see the plus button menu

    Examples:
      | content creation           | expected   |
      | "registered users"         | should     |
      | "facilitators and authors" | should not |

  @javascript
  Scenario: A member can add a layout to new content
    And solution content:
      | title               |
      | NexaFix Innovations |
    And the "content_creation" setting of the "NexaFix Innovations" solution is set to "registered users"
    Given I am logged in as a member of the "NexaFix Innovations" solution
    And I go to the "NexaFix Innovations" solution
    When I click "Add news" in the plus button menu
    And I click "Add layout"
    And a modal should open
