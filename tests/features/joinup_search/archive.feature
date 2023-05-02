@api @terms @group-d
Feature: Global search with archive content

  Scenario: Collection content has an 'Show archived' facet.
    Given the following owner:
      | name       | type    |
      | Some owner | Company |
    And the following contact:
      | name  | Some contact             |
      | email | some-contact@example.com |
    And the following collections:
      | title            | description | abstract                       | state     |
      | Some collection | Description | The collection gamma abstract. | validated |
    And event content:
      | title   | short title | body      | spatial coverage | agenda      | location   | organisation        | scope         | collection      | state     |
      | Event 1 | Event 1     | Some body | Greece           | Some agenta | Some place | European Commission | International | Some collection | validated |
    And document content:
      | title           | document type | short title     | body      | spatial coverage | collection      | state     |
      | Some document 1 | Document      | Some document 1 | Some body | Luxembourg       | Some collection | validated |

    When the "Some collection" collection is configured to show "All" community content in the overview
    And the "Some collection" collection is configured to show the "Collection content archived toggle" facet in the "Top" region
    When I go to the homepage of the "Some collection" collection
    Then I should not see the "collection content archived toggle" facet

    Given event content:
      | title   | short title | body      | spatial coverage | agenda      | location   | organisation        | scope         | collection      | state     |
      | Event 2 | Event 2     | Some body | Greece           | Some agenta | Some place | European Commission | International | Some collection | archived |
    And document content:
      | title           | document type | short title     | body      | spatial coverage | collection      | state     |
      | Some document 2 | Document      | Some document 2 | Some body | Luxembourg       | Some collection | archived |
    When I go to the homepage of the "Some collection" collection
    Then I should see the "collection content archived toggle" facet
