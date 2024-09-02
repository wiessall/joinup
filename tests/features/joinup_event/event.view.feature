@api @group-e
Feature: View discussion API
  As a visitor of the website I can view a discussion content.

  Scenario: User view view Event entity
    Given collection content:
      | title            | state     |
      | Le Event Heureux | published |
    And event content:
      | title       | state     | short title | field_paragraphs_body                    | collection       | start date          | end date            | field_event_contact_email |
      | Dummy Event | published | Short       | This is some dummy content like foo:bar. | Le Event Heureux | 2016-03-15T11:12:12 | 2016-03-15T11:12:12 | reach.me@example.com      |

    When I go to the "Dummy Event" event
    And I should see the link "reach.me@example.com"
    And the page should contain the html text '<a href="mailto:reach.me@example.com">'
