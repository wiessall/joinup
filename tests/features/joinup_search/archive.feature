@api @terms @group-f
Feature: Global search with archive content

  Scenario Outline: Group content has an 'Show archived' facet.
    Given the following owner:
      | name       | type    |
      | Some owner | Company |
    And the following contact:
      | name  | Some contact             |
      | email | some-contact@example.com |
    And the following <group type>s:
      | title             | description | state     |
      | Some <group type> | Description | validated |
    And event content:
      | title   | short title | body      | spatial coverage | agenda      | location   | organisation        | scope         | <group type>      | state     |
      | Event 1 | Event 1     | Some body | Greece           | Some agenta | Some place | European Commission | International | Some <group type> | validated |
    And document content:
      | title           | document type | short title     | body      | spatial coverage | <group type>      | state     |
      | Some document 1 | Document      | Some document 1 | Some body | Luxembourg       | Some <group type> | validated |

    When the "Some <group type>" <group type> is configured to show "All" community content in the overview
    And the "Some <group type>" <group type> is configured to show the "<bundle label> content archived toggle" facet in the "Top" region
    When I go to the homepage of the "Some <group type>" <group type>
    Then I should not see the "<group type> content archived toggle" facet
    And I should see the following tiles in the correct order:
      | Event 1         |
      | Some document 1 |

    Given event content:
      | title   | short title | body      | spatial coverage | agenda      | location   | organisation        | scope         | <group type>      | state    |
      | Event 2 | Event 2     | Some body | Greece           | Some agenta | Some place | European Commission | International | Some <group type> | archived |
    And document content:
      | title           | document type | short title     | body      | spatial coverage | <group type>      | state    |
      | Some document 2 | Document      | Some document 2 | Some body | Luxembourg       | Some <group type> | archived |
    When I go to the homepage of the "Some <group type>" <group type>
    Then I should see the "<group type> content archived toggle" facet
    And I should see the following tiles in the correct order:
      | Event 1         |
      | Some document 1 |

    # By default, the toggle is inactive.
    But I should not see the "Event 2" tile
    And I should not see the "Some document 2" tile

    # When the toggle is active, archived content is shown.
    When I click "Show archived"
    Then I should see the following tiles in the correct order:
      | Event 2         |
      | Some document 2 |

    And I should not see the "Event 1" tile
    And I should not see the "Some document 1" tile

    Examples:
      | bundle label | group type |
      | Collection   | collection |
      | Solution     | solution   |
