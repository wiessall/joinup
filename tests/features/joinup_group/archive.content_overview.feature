@api @group-i
Feature: Global search with archive content

  Scenario Outline: Group content has an 'Show archived' facet.
    Given owner content:
      | name       | owner type |
      | Some owner | Company    |
    And contact_information content:
      | name         | email                    |
      | Some contact | some-contact@example.com |
    And the following <group type>s:
      | title             | description | state     |
      | Some <group type> | Description | published |
      | Rare <group type> | Description | published |
    And event content:
      | title   | short title | field_paragraphs_body | spatial coverage | agenda      | location   | organisation        | scope         | <group type>      | state     |
      | Event 1 | Event 1     | Some body             | Greece           | Some agenta | Some place | European Commission | International | Some <group type> | published |
      | Event 2 | Event 2     | Some body             | Greece           | Some agenta | Some place | European Commission | International | Rare <group type> | published |
      | Event 3 | Event 3     | Some body             | Greece           | Some agenta | Some place | European Commission | International | Rare <group type> | published |
      | Event 4 | Event 4     | Some body             | Greece           | Some agenta | Some place | European Commission | International | Rare <group type> | published |
    And document content:
      | title           | document type | short title     | field_paragraphs_body | spatial coverage | <group type>      | state     |
      | Some document 1 | Document      | Some document 1 | Some body             | Luxembourg       | Some <group type> | published |

    When the "Some <group type>" <group type> is configured to show "All" community content in the overview
    And the "Some <group type>" <group type> is configured to show the "<bundle label> content archived toggle" facet in the "Top" region
    When the "Rare <group type>" <group type> is configured to show "All" community content in the overview
    And the "Rare <group type>" <group type> is configured to show the "<bundle label> content archived toggle" facet in the "Top" region
    When I go to the homepage of the "Some <group type>" <group type>
    Then I should not see the "<group type> content archived toggle" facet
    And I should see the following tiles in the correct order:
      | Event 1         |
      | Some document 1 |

    When I go to the homepage of the "Rare <group type>" <group type>
    Then I should not see the "<group type> content archived toggle" facet

    Given event content:
      | title   | short title | field_paragraphs_body | spatial coverage | agenda      | location   | organisation        | scope         | <group type>      | state    |
      | Event 2 | Event 2     | Some body             | Greece           | Some agenta | Some place | European Commission | International | Some <group type> | archived |
    And document content:
      | title           | document type | short title     | field_paragraphs_body | spatial coverage | <group type>      | state    |
      | Some document 2 | Document      | Some document 2 | Some body             | Luxembourg       | Some <group type> | archived |
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
    And I should see the text "Archived" in the "Event 2" tile
    And I should see the text "Archived" in the "Some document 2" tile

    And I should not see the "Event 1" tile
    And I should not see the "Some document 1" tile

    Given discussion content:
      | title               | body           | state    | <group type>      | topic         | created          | changed                   |
      | Archived discussion | No body needed | archived | Some <group type> | Finance in EU | 2017-11-11T11:11 | Wed, 25 Dec 2019 15:00:00 |
    And news content:
      | title         | body           | state    | <group type>      | changed    | topic             |
      | Archived news | No body needed | archived | Some <group type> | 2019-11-19 | Supplier exchange |

    When I go to the homepage of the "Some <group type>" <group type>
    Then I should not see the "Archived discussion" tile
    And I should not see the "Archived news" tile

    When I click "Show archived"
    Then I should see the following tiles in the correct order:
      | Event 2         |
      | Some document 2 |
      | Archived news   |
    # Discussions do not show in the overview anyway.
    But I should not see the "Archived discussion" tile

    When I go to the homepage of the "Rare <group type>" <group type>
    Then I should not see the "<group type> content archived toggle" facet

    Examples:
      | bundle label | group type |
      | Collection   | collection |
      | Solution     | solution   |

  Scenario: Distributions directly under a solution respect the state.
    And the following solutions:
      | title         | description | state     |
      | Some solution | Description | published |
    And distribution content:
      | title                 | description | state     | parent        |
      | Some zip distribution | Description | published | Some solution |
      | Some rar distribution | Description | archived  | Some solution |
    And the "Some solution" solution is configured to show "All" community content in the overview

    When I go to the homepage of the "Some solution" solution
    Then I should see the following tiles in the correct order:
      | Some zip distribution |
    And I should not see the "Some rar distribution" tile
    When I click "Show archived"
    Then I should see the following tiles in the correct order:
      | Some rar distribution |
    And I should not see the "Some zip distribution" tile

  Scenario: Group content supports archived solutions.
    Given owner content:
      | name       |
      | Erno Rubik |
    And contact_information content:
      | name       |
      | Tom Kremer |
    And collections:
      | title      | description                | topic             | owner      | contact information | state     |
      | Rubik Cube | combination puzzle         | Supplier exchange | Erno Rubik | Tom Kremer          | published |
      | Speed cube | also known as speedsolving | Supplier exchange | Erno Rubik | Tom Kremer          | published |
    And solutions:
      | title    | state     | collection | shared on  |
      | Megaminx | published | Rubik Cube | Speed cube |
      | 4x4 cube | archived  | Rubik Cube | Speed cube |
    And the "Rubik Cube" collection is configured to show "All" community content
    And the "Speed cube" collection is configured to show "All" community content

    And I go to the homepage of the "Rubik Cube" collection
    Then I should see the following tiles in the correct order:
      | Megaminx |
    And I should not see the text "Archived" in the "Megaminx" tile
    When I click "Show archived"
    Then I should see the following tiles in the correct order:
      | 4x4 cube |
    And I should see the text "Archived" in the "4x4 cube" tile

    And I go to the homepage of the "Speed cube" collection
    When I click "Show archived"
    Then I should see the following tiles in the correct order:
      | 4x4 cube |
