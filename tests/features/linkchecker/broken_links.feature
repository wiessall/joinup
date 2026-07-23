@api @group-j
Feature: Broken links report

  Background:
    Given the following "collection" content:
      | title              |
      | Broken links group |

  Scenario: A broken link in a field directly on a node is attributed to the node, and unpublished content is skipped.
    Given the following "event" content:
      | title         | collection         | start date          | end date            | state     | field_event_agenda                                     |
      | Faulty agenda | Broken links group | 2020-01-01T11:00:00 | 2020-01-01T12:00:00 | published | <p><a href="/broken-event-agenda">Agenda</a></p>       |
      | Draft agenda  | Broken links group | 2020-01-01T11:00:00 | 2020-01-01T12:00:00 | draft     | <p><a href="/broken-draft-agenda">Draft agenda</a></p> |
    And I run the "linkchecker_cron" ultimate_cron job
    When I am logged in as a moderator
    And I go to "admin/reports/linkchecker"
    Then I should see the link "Faulty agenda"
    And I should see the text "field_event_agenda"
    But I should not see the link "Draft agenda"

  Scenario: A broken link in a paragraph is attributed to the node it belongs to.
    Given the following "custom_page" content:
      | title       | collection         |
      | Faulty text | Broken links group |
    And I append "text" to "field_paragraphs_body" field in "node" entity with following "Faulty text" title:
      | field_body | <p><a href="/broken-text-paragraph">Broken text link</a></p> |
    And I run the "linkchecker_cron" ultimate_cron job
    When I am logged in as a moderator
    And I go to "admin/reports/linkchecker"
    Then I should see the link "Faulty text"
    And I should see the text "field_body"
