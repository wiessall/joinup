@api @group-j
Feature: Broken links report

  Background:
    Given the following "collection" content:
      | title              |
      | Broken links group |

  Scenario: A broken link in a field directly on a node is attributed to the node, and unpublished or translated content is skipped.
    Given the following "event" content:
      | title         | collection         | start date          | end date            | state     | field_event_agenda                                     |
      | Faulty agenda | Broken links group | 2020-01-01T11:00:00 | 2020-01-01T12:00:00 | published | <p><a href="/broken-event-agenda">Agenda</a></p>       |
      | Draft agenda  | Broken links group | 2020-01-01T11:00:00 | 2020-01-01T12:00:00 | draft     | <p><a href="/broken-draft-agenda">Draft agenda</a></p> |
    And the following event entity translation:
      | source        | language | field_event_agenda                                  |
      | Faulty agenda | hu       | <p><a href="/hu/broken-event-agenda">Agenda</a></p> |
    When I am logged in as a moderator
    And I go to "admin/reports/linkchecker"
    Then I should see the link "Faulty agenda"
    But I should not see the link "Draft agenda"
    And I should not see the text "/hu/broken-event-agenda"

  Scenario: A broken link in a paragraph is attributed to the node it belongs to.
    Given the following "custom_page" content:
      | title       | collection         |
      | Faulty text | Broken links group |
    And I append "text" to "field_paragraphs_body" field in "node" entity with following "Faulty text" title:
      | field_body | <p><a href="/broken-text-paragraph">Broken text link</a></p> |
    When I am logged in as a moderator
    And I go to "admin/reports/linkchecker"
    Then I should see the link "Faulty text"

  @javascript
  Scenario: A broken link in a paragraph nested inside an accordion is attributed to the node it belongs to.
    Given I am logged in as a moderator
    And I go to the "Broken links group" collection
    And I open the plus button menu
    And I click "Add custom page"
    And I fill in "Title" with "Broken accordion page"
    And I fill in "Abstract" with "Broken accordion abstract"
    And I select "Demography" options from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout | one column |
    And I click "Choose component"
    And a modal should open
    And I click "Accordion"
    And I fill in the 1st "Item label" with "Broken section" in the "Accordion item" field
    And I enter "<p><a href='/broken-accordion-paragraph'>Broken accordion link</a></p>" in the 1st "Body" wysiwyg editor in the "Accordion item" field
    And I press "Save" in the "Modal buttons" region
    And I press "Publish"
    When I go to "admin/reports/linkchecker"
    Then I should see the link "Broken accordion page"
