@api @group-a
Feature: Content Creation Permissions
  As a group member,
  I want to have content creation permissions based on the solution's settings,
  So that I can add content when allowed.

  Scenario Outline: Content creation availability based on solution settings
    And the following "solution" content:
      | title               | content_creation   |
      | ResolveTech Systems | <content creation> |

    Given I am logged in as a member of the "ResolveTech Systems" solution
    And I go to the "ResolveTech Systems" solution
    Then I <expected> see the plus button menu

    Examples:
      | content creation         | expected   |
      | registered users         | should     |
      | facilitators and authors | should not |

  @javascript
  Scenario: A member can add a layout to new content
    And the following "solution" content:
      | title               | content_creation |
      | NexaFix Innovations | registered users |

    Given I am logged in as a member of the "NexaFix Innovations" solution
    And I go to the "NexaFix Innovations" solution
    When I click "Add news" in the plus button menu
    And I click "Add layout"
    And a modal should open
