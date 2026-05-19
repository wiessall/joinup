@api @group-c
Feature:
  In order to have better presentation of my content
  as a user of the website
  I need to be able to see the content listed in the appropriate view mode.

  Scenario: Present custom page solution listings in tiles.
    Given the following "solution" content:
      | title         | description |
      | Tile solution | Meh...      |
    And the following "release" content:
      | title        | release number | release notes | is version of |
      | Tile release | 0.1            | Meh...        | Tile solution |
    And the following "distribution" content:
      | title       | description | release      | resources |
      | Tile distro | meh...      | Tile release | test.zip  |
    And the following "licence" content:
      | title        | description |
      | Tile licence | Meh...      |
    And the following "news" content:
      | title     | headline  | body   | solution      |
      | Tile news | Tile news | Meh... | Tile solution |
    And the following "event" content:
      | title      | short title | field_paragraphs_body | agenda        | location   | organisation        | scope         | solution      |
      | Tile event | Tile event  | Meh...                | Event agenda. | Some place | European Commission | International | Tile solution |
    And the following "document" content:
      | title         | document type | short title | field_paragraphs_body | solution      |
      | Tile document | Document      | Meh...      | Meh...                | Tile solution |
    And the following "discussion" content:
      | title           | body   | solution      |
      | Tile discussion | Meh... | Tile solution |
    And the following "custom_page" content:
      | title            | solution      |
      | Tile custom page | Tile solution |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Tile custom page" title:
      | content_listing:value | a:3:{s:7:"enabled";i:1;s:13:"query_presets";s:68:"entity_bundle\|distribution,release,discussion,document,event,news\|IN";s:5:"limit";s:2:"10";} |

    When I am logged in as a moderator
    And I go to the "Tile custom page" custom_page

    Then I should see the "Tile release" tile
    And I should see the text "v0.1" in the "Tile release" tile
    And I should see the "Tile distro" tile
    And I should see the "Tile news" tile
    And I should see the "Tile event" tile
    And I should see the "Tile document" tile
    And I should see the "Tile discussion" tile
    But I should not see the "Tile licence" tile

  Scenario: Present custom page collection listings in tiles.
    And the following "collection" content:
      | title           | description |
      | Tile collection | Meh...      |
    And the following "news" content:
      | title     | headline  | body   | collection      |
      | Tile news | Tile news | Meh... | Tile collection |
    And the following "event" content:
      | title      | short title | field_paragraphs_body | agenda        | location   | organisation        | scope         | collection      |
      | Tile event | Tile event  | Meh...                | Event agenda. | Some place | European Commission | International | Tile collection |
    And the following "document" content:
      | title         | document type | short title | field_paragraphs_body | collection      |
      | Tile document | Document      | Meh...      | Meh...                | Tile collection |
    And the following "discussion" content:
      | title           | body   | collection      |
      | Tile discussion | Meh... | Tile collection |
    And the following "custom_page" content:
      | title            | collection      |
      | Tile custom page | Tile collection |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Tile custom page" title:
      | content_listing:value | a:3:{s:7:"enabled";i:1;s:13:"query_presets";s:47:"entity_bundle\|discussion,document,event,news\|IN";s:5:"limit";s:2:"10";} |

    When I am logged in as a moderator
    And I go to the "Tile custom page" custom_page

    And I should see the "Tile news" tile
    And I should see the "Tile event" tile
    And I should see the "Tile document" tile
    And I should see the "Tile discussion" tile
