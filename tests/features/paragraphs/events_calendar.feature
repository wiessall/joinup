@api @group-h @javascript
Feature:
  As a Portal's collection owner or facilitator,
  I want to add a calendar with all Portal's events in my collection,
  so that users of the portal can see forthcoming events.

  Background:
    Given the following "collection" content:
      | title            |
      | Le Event Heureux |
      | Collection two   |
    And the following "event" content:
      | title                      | collection       | start date         | end date            |
      | Old month event            | Le Event Heureux | now -2 month       | now -2 month +1 day |
      | Upcoming month event       | Le Event Heureux | now +1 month       | now +1 month +2 day |
      | Old week event             | Le Event Heureux | now -1 week        | now -1 week +2 day  |
      | Upcoming week Event        | Le Event Heureux | now +1 week        | now +2 week +1 day  |
      | Dummy old month event      | Collection two   | now -2 month       | now -2 month +1 day |
      | Dummy upcoming month event | Collection two   | now +1 month       | now +1 month +1 day |
      | Dummy old week event       | Collection two   | now -1 week        | now -1 week +2 day  |
      | Dummy upcoming week event  | Collection two   | now +1 week -1 day | now +1 week +1 day  |

  Scenario: Test that Events calendar with a specific event is working correctly in monthly mode.
    When I am logged in as a facilitator of the "Le Event Heureux" collection
    And I go to the "Le Event Heureux" collection
    And I click "Add custom page" in the plus button menu
    When I fill in the following:
      | Title    | Page                           |
      | Abstract | Abstract pages Events calendar |
    And I select "Demography" options from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Events calendar"
    And I select "Calendar by month" from "Block"
    And I press "Save" in the "Modal buttons" region
    And I press "Publish"
    Then I should be on the Page content page
    Then I should see the calendar showing all days of current month
    When I navigate the calendar to "now -2 month" in month mode
    Then I see the "Old month event" showing in the Calendar
    And I see the "Dummy old month event" showing in the Calendar
    When I navigate the calendar to "now +1 month" in month mode
    Then I see the "Upcoming month event" showing in the Calendar
    And I see the "Dummy upcoming month event" showing in the Calendar
    And I click "Upcoming month event"
    Then I should be on the "Upcoming month event" content page

  Scenario: Test that Events calendar with a specific event is working correctly in weekly mode.
    When I am logged in as a facilitator of the "Le Event Heureux" collection
    And I go to the "Le Event Heureux" collection
    And I click "Add custom page" in the plus button menu
    When I fill in the following:
      | Title    | Page 2                         |
      | Abstract | Abstract pages Events calendar |
    And I select "Demography" options from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Events calendar"
    And I select "Calendar by week" from "Block"
    And I press "Save" in the "Modal buttons" region
    And I press "Publish"
    Then I should be on the "Page 2" content page
    Then I should see the calendar showing all days of current week
    When I navigate the calendar to "now -1 week" in week mode
    Then I see the "Old week event" showing in the Calendar
    And I see the "Dummy old week event" showing in the Calendar
    When I navigate the calendar to "now +1 week" in week mode
    Then I see the "Upcoming week Event" showing in the Calendar
    And I see the "Dummy upcoming week event" showing in the Calendar
    And I click "Upcoming week Event"
    Then I should be on the "Upcoming week Event" content page

  Scenario: Test that location type labels are shown correctly in the calendar.
    Given the following "custom_page" content:
      | title                | abstract                       | collection       |
      | Location labels page | Abstract pages Events calendar | Le Event Heureux |
    And the following "event" content:
      | title               | collection       | start date | end date | location          | online location                           |
      | Physical label test | Le Event Heureux | now        | now      | Brussels, Belgium |                                           |
      | Virtual label test  | Le Event Heureux | now        | now      |                   | 0: Online meeting - 1: http://example.com |
      | Hybrid label test   | Le Event Heureux | now        | now      | Brussels, Belgium | 0: Online meeting - 1: http://example.com |
    And I append "events_calendar" to "field_paragraphs_body" field in "node" entity with following "Location labels page" title:
      | paragraph_block | views_block:calendar-by_month |
    And I visit the "Location labels page" "custom page"
    Then the "Physical label test" event in the Calendar contains the text "Physical event"
    And the "Virtual label test" event in the Calendar contains the text "Virtual event"
    And the "Hybrid label test" event in the Calendar contains the text "Hybrid event"

  Scenario: Test upcoming events page and paragraph.
    Given the following "custom_page" content:
      | title                  | abstract                        | collection       |
      | Future events calendar | Abstract future events calendar | Le Event Heureux |
      | Upcoming events        | Upcoming events page            |                  |
      | Upcoming EC events     | Upcoming EC events page         |                  |
    And the following "event" content:
      | title            | collection       | start date         | end date           | european_commission_digit_event |
      | Upcoming Event 1 | Le Event Heureux | now +1 day         | now +5 day         | True                            |
      | Upcoming Event 3 | Le Event Heureux | now -12 day        | now -14 day        | False                           |
      | Dummy Event 4    | Collection two   | now +1 week +1 day | now +1 week +2 day | False                           |
      | Dummy Event 5    | Collection two   | now -1 week        | now +2 week        | False                           |
    When I am logged in as a facilitator of the "Le Event Heureux" collection
    Then I go to the edit form of the "Future events calendar" "custom page"
    And I select "Demography" options from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Events calendar"
    And I select "Upcoming events (Group view)" from "Block"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the text "Future events calendar" in the "Page title" region
    And I should see the following tiles in the correct order:
      | Upcoming Event 1     |
      | Upcoming week Event  |
      | Upcoming month event |

    When I am logged in as a moderator
    Then I visit "/admin/custom-landing-pages/add"
    And I fill in "Upcoming events" for "Title"
    And I fill in "Upcoming events page" for "Abstract"
    And I select "Demography" options from "Topic"
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
    Then I should see the message "Custom page Upcoming events has been created."
    And I should see the text "Upcoming events" in the "Page title" region
    And I should see the following tiles in the correct order:
      | Dummy Event 5              |
      | Upcoming Event 1           |
      | Dummy upcoming week event  |
      | Upcoming week Event        |
      | Dummy Event 4              |
      | Upcoming month event       |
      | Dummy upcoming month event |

    Then I go to the edit form of the "Upcoming EC events" "custom page"
    And I select "Demography" options from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Events calendar"
    And I select "Upcoming EC events" from "Block"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the text "Upcoming EC events" in the "Page title" region
    Then the page should show only the tiles "Upcoming Event 1"
    And I should not see "Upcoming Event 3"
