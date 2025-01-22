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
      | title         | short title | field_paragraphs_body                    | collection       | start date          | end date            | field_event_contact_email |
      | Dummy Event   | Short       | This is some dummy content like foo:bar. | Le Event Heureux | 2025-12-15T11:12:12 | 2025-12-15T11:12:12 | reach.me@example.com      |
      | Dummy Event 2 | Short       | random text                              | Collection 2     | 2025-12-16T11:12:12 | 2025-12-16T11:12:12 | reach.me@example.com      |
      | Dummy Event 3 | Short       | internal meeting                         | Collection 3     | 2025-12-17T11:12:12 | 2025-12-17T11:12:12 | reach.me@example.com      |

  Scenario: Test that Events calendar with a specific event is working correctly.
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
    And I select the next month repeatedly until I reach December
#    Then I see the "Dummy Event " showing in the Calendar
#    And I press on "Dummy Event"
#    Then the "Dummy Event" canonical page is shown
