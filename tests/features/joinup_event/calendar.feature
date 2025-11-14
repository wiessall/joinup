@api @group-a @javascript
Feature:
  As a Portal's collection owner or facilitator,
  I want to add a calendar with all Portal's events in my collection,
  so that users of the portal can see forthcoming events.

  Background:
    Given "event" content:
      | title                         | start date          | end date            |
      | TechWizards Summit            | 2020-01-15 12:00:00 | 2020-01-15 13:00:00 |
      | Creative Fusion Fest          | 2020-01-08 12:00:00 | 2020-01-08 13:00:00 |
      | Code & Coffee Hackathon       | 2020-01-22 12:00:00 | 2020-01-22 13:00:00 |
      | Future Horizons Expo          | 2019-12-15 12:00:00 | 2019-12-15 13:00:00 |
      | Innovate & Elevate Conference | 2020-02-15 12:00:00 | 2020-02-15 13:00:00 |

  # Validate the need for the "@restart-session" tag in ISAICP-9742.
  @restart-session
  Scenario Outline: User can navigate through events
    Given custom_page content:
      | title           | collection | abstract        |
      | Events calendar | Portal support | events calendar |
    And I append "events_calendar" to "field_paragraphs_body" field in "node" entity with following "Events calendar" title:
      | paragraph_block | <calendar type> |

    #  Start from Wed Jan 15 2020 11:00:00 GMT+0000
    When I visit "/collection/portal-support/events-calendar?calendar_timestamp=1579086000"
    Then I should see the link "TechWizards Summit"
    And I should not see the link "<previous event>"
    And I should not see the link "<next event>"

    When I click "Previous"
    Then I should see the link "<previous event>"
    And I should not see the link "<next event>"

    #  Start again from Wed Jan 15 2020 11:00:00 GMT+0000
    When I visit "/collection/portal-support/events-calendar?calendar_timestamp=1579086000"
    And I click "Next"
    Then I should see the link "<next event>"
    And I should not see the link "<previous event>"

    Examples:
      | calendar type                 | previous event       | next event                    |
      | views_block:calendar-by_week  | Creative Fusion Fest | Code & Coffee Hackathon       |
      | views_block:calendar-by_month | Future Horizons Expo | Innovate & Elevate Conference |
