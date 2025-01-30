@api @group-h @javascript
Feature:
  As a Portal's collection owner or facilitator,
  I want to add a calendar with all Portal's events in my collection,
  so that users of the portal can see forthcoming events.

  Background:
    Given collection content:
      | title            |
      | Le Event Heureux |
      | Collection 2     |
      | Collection 3     |
    And event content:
      | title          | short title | field_paragraphs_body                    | collection       | start date   | end date             | field_event_contact_email |
      | Archived Event | Short       | This is some dummy content like foo:bar. | Le Event Heureux | now -1 month | now -1 month         | reach.me@example.com      |
      | Upcoming Event | Short       | This is some dummy content like foo:bar. | Le Event Heureux | now +1 month | now +1 month +2 days | reach.me@example.com      |
      | Dummy Event 2  | Short       | random text                              | Collection 2     | now +1 week  | now +2 week          | reach.me@example.com      |
      | Dummy Event 3  | Short       | internal meeting                         | Collection 3     | now -1 week  | now -1 week +1 day   | reach.me@example.com      |

  Scenario: Test that Events calendar with a specific event is working correctly in monthly mode
    When I am logged in as a facilitator of the "Le Event Heureux" collection
    And I go to the "Le Event Heureux" collection
    And I click "Add custom page" in the plus button menu
    When I fill in the following:
      | Title    | Page                           |
      | Abstract | Abstract pages Events calendar |
    And I select "HR" from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Events calendar"
    And I select "Calendar: Calendar by month" from "Block"
    And I press "Save" in the "Modal buttons" region
    And I press "Publish"
    Then I should be on the Page content page
    Then I should see the calendar showing all days of current month
    When I navigate the calendar to "now -1 month" in month mode
    Then I see the "Archived Event" showing in the Calendar
    When I navigate the calendar to "now +1 month" in month mode
    Then I see the "Dummy Event 2" showing in the Calendar
    Then I not see the "Upcoming Event" showing in the Calendar
    Then I not see the "Dummy Event 3" showing in the Calendar
    And I click "Dummy Event 2"
    Then I should be on the "Dummy Event 2" content page

  Scenario: Test that Events calendar with a specific event is working correctly in weekly mode.
    When I am logged in as a facilitator of the "Le Event Heureux" collection
    And I go to the "Le Event Heureux" collection
    And I click "Add custom page" in the plus button menu
    When I fill in the following:
      | Title    | Page 2                         |
      | Abstract | Abstract pages Events calendar |
    And I select "HR" from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Events calendar"
    And I select "Calendar: Calendar by week" from "Block"
    And I press "Save" in the "Modal buttons" region
    And I press "Publish"
    Then I should be on the "Page 2" content page
    Then I should see the calendar showing all days of current week
    When I navigate the calendar to "now -1 week" in week mode
    Then I see the "Dummy Event 3" showing in the Calendar
    When I navigate the calendar to "now +2 week" in week mode
    Then I see the "Dummy Event 2" showing in the Calendar
    And I click "Dummy Event 2"
    Then I should be on the "Dummy Event 2" content page

  Scenario: Test upcoming events page and paragraph.
    Given event content:
      | title            | short title | field_paragraphs_body                    | collection       | start date  | end date    | field_event_contact_email |
      | Upcoming Event 1 | Short       | This is some dummy content like foo:bar. | Le Event Heureux | now +2 day  | now +5 day  | reach.me@example.com      |
      | Upcoming Event 3 | Short       | This is some dummy content like foo:bar. | Le Event Heureux | now -12 day | now -14 day | reach.me@example.com      |
      | Dummy Event 4    | Short       | random text event                        | Collection 2     | now +1 week | now +1 week | reach.me@example.com      |
      | Dummy Event 5    | Short       | infernal meeting                         | Collection 3     | now -1 week | now +2 week | reach.me@example.com      |
    When I am logged in as a facilitator of the "Le Event Heureux" collection
    And I go to the "Le Event Heureux" collection
    And I click "Add custom page" in the plus button menu
    When I fill in the following:
      | Title    | Future events calendar          |
      | Abstract | Abstract future events calendar |
    And I select "HR" from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Events calendar"
    And I select "Upcoming events" from "Block"
    And I press "Save" in the "Modal buttons" region
    And I press "Publish"
    Then I should see the text "Future events calendar" in the "Page title" region
    And I should see the text "Upcoming events"
    And I should see the text "Check out our 2025 events"
    And I should see the following tiles in the correct order:
      | Upcoming Event 1 |
      | Upcoming Event   |

    When I go to "/upcoming-events"
    Then I should see the text "Upcoming events" in the "Page title" region
    And I should see the text "Check out our 2025 events"
    And I should see the following tiles in the correct order:
      | Upcoming Event 1 |
      | Dummy Event 2    |
      | Dummy Event 4    |
      | Upcoming Event   |
