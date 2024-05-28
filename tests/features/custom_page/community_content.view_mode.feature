@api @group-b
Feature:
  In order to have better presentation of my content
  as a user of the website
  I need to be able to see the content listed in the appropriate view mode.

  Scenario: Present custom page solution listings in tiles.
    Given owner content:
      | name                   | owner type |
      | Some fancy named owner | Company    |
    And contact_information content:
      | name                     | email                 |
      | Some fancy named contact | who.cares@example.com |
    And the following solutions:
      | title         | description | owner                  | contact information      | state     |
      | Tile solution | Meh...      | Some fancy named owner | Some fancy named contact | published |
    And the following releases:
      | title        | release number | release notes | is version of | owner                  | contact information      | state     |
      | Tile release | 0.1            | Meh...        | Tile solution | Some fancy named owner | Some fancy named contact | published |
    And the following distributions:
      | title       | description | parent       | access url | state     |
      | Tile distro | meh...      | Tile release | test.zip   | published |
    And licence content:
      | title        | description |
      | Tile licence | Meh...      |
    And news content:
      | title     | headline  | body   | solution      | state     |
      | Tile news | Tile news | Meh... | Tile solution | published |
    And event content:
      | title      | short title | field_paragraphs_body | agenda        | location   | organisation        | scope         | solution      | state     |
      | Tile event | Tile event  | Meh...                | Event agenda. | Some place | European Commission | International | Tile solution | published |
    And document content:
      | title         | document type | short title | field_paragraphs_body | solution      | state     |
      | Tile document | Document      | Meh...      | Meh...                | Tile solution | published |
    And discussion content:
      | title           | body   | solution      | state     |
      | Tile discussion | Meh... | Tile solution | published |
    And custom_page content:
      | title            | solution      | state     |
      | Tile custom page | Tile solution | published |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Tile custom page" title:
      | content_listing:value | a:3:{s:7:"enabled";i:1;s:13:"query_presets";s:80:"entity_bundle\|asset_distribution,asset_release,discussion,document,event,news\|IN";s:5:"limit";s:2:"10";} |

    When I am logged in as a moderator
    And I go to the "Tile custom page" custom page

    Then I should see the "Tile release" tile
    And I should see the text "v0.1" in the "Tile release" tile
    And I should see the "Tile distro" tile
    And I should see the "Tile news" tile
    And I should see the "Tile event" tile
    And I should see the "Tile document" tile
    And I should see the "Tile discussion" tile
    But I should not see the "Tile licence" tile

  Scenario: Present custom page collection listings in tiles.
    Given owner content:
      | name                   | owner type |
      | Some fancy named owner | Company    |
    And contact_information content:
      | name                     | email                 |
      | Some fancy named contact | who.cares@example.com |
    And the following collections:
      | title           | description | owner                  | contact information      | state     |
      | Tile collection | Meh...      | Some fancy named owner | Some fancy named contact | published |
    And news content:
      | title     | headline  | body   | collection      | state     |
      | Tile news | Tile news | Meh... | Tile collection | published |
    And event content:
      | title      | short title | field_paragraphs_body | agenda        | location   | organisation        | scope         | collection      | state     |
      | Tile event | Tile event  | Meh...                | Event agenda. | Some place | European Commission | International | Tile collection | published |
    And document content:
      | title         | document type | short title | field_paragraphs_body | collection      | state     |
      | Tile document | Document      | Meh...      | Meh...                | Tile collection | published |
    And discussion content:
      | title           | body   | collection      | state     |
      | Tile discussion | Meh... | Tile collection | published |
    And custom_page content:
      | title            | collection      | state     |
      | Tile custom page | Tile collection | published |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Tile custom page" title:
      | content_listing:value | a:3:{s:7:"enabled";i:1;s:13:"query_presets";s:47:"entity_bundle\|discussion,document,event,news\|IN";s:5:"limit";s:2:"10";} |

    When I am logged in as a moderator
    And I go to the "Tile custom page" custom page

    And I should see the "Tile news" tile
    And I should see the "Tile event" tile
    And I should see the "Tile document" tile
    And I should see the "Tile discussion" tile
