@api @group-b
Feature: Create community content through the API
  In order to quickly generate community content for testing purposes
  As a developer for the Joinup platform
  I need to be able to create community content through the API

  Scenario Outline: Publication state of community content created through the API
    Given collection:
      | title | End user documentation |
      | state | published              |
    And "<type>" content:
      | title   | body | collection             | field_state      |
      | <title> | body | End user documentation | <workflow state> |
    Then the community content with title "<title>" should have the publication state "<publication state>"

    Examples:
      | type       | workflow state   | publication state | title                    |
      | discussion | needs_update     | unpublished       | discussion-needs-update  |
      | discussion | proposed         | unpublished       | discussion-proposed      |
      | discussion | published        | published         | discussion-published     |
      | discussion | archived         | published         | discussion-archived      |
      | document   | draft            | unpublished       | document-draft           |
      | document   | needs_update     | unpublished       | document-needs-update    |
      | document   | proposed         | unpublished       | document-proposed        |
      | document   | published        | published         | document-published       |
      | document   | deletion_request | unpublished       | document-archived        |
      | event      | draft            | unpublished       | event-draft              |
      | event      | needs_update     | unpublished       | event-needs-update       |
      | event      | proposed         | unpublished       | event-proposed           |
      | event      | published        | published         | event-published          |
      | event      | deletion_request | unpublished       | event-archived           |
      | news       | draft            | unpublished       | news-draft               |
      | news       | needs_update     | unpublished       | news-needs-update        |
      | news       | proposed         | unpublished       | news-proposed            |
      | news       | published        | published         | news-published           |
      | news       | deletion_request | unpublished       | news-archived            |

