@api @terms @group-d
Feature: Global search with archive content

  @javascript
  Scenario: Anonymous user can find items
    Given the following owner:
      | name              | type    |
      | Responsible owner | Company |
    And the following contact:
      | name  | Go-to contact     |
      | email | go-to@example.com |
    And the following collections:
      | title            | description                                          | abstract                       | state     |
      | Collection alpha | <p>collection <strong>beta</strong> description.</p> | The collection gamma abstract. | validated |
      | Run stars        | <p>collection for the solution.</p>                  | The col for sol abstract.      | archived  |
      | Col for Sol      | <p>collection for the solution.</p>                  | The col for sol abstract.      | validated |
    And event content:
      | title             | short title       | body                                | spatial coverage | agenda         | location       | organisation        | scope         | keywords | collection       | state     |
      | Event Omega       | Event short delta | The epsilon event content.          | Greece           | Event agenda.  | Some place     | European Commission | International | Alphabet | Collection alpha | validated |
      | Alternative event | Alt event         | This event stays in the background. | Luxembourg       | To be planned. | Event location | Event organisation  |               |          | Collection alpha | validated |
    And document content:
      | title          | document type | short title          | body                                    | spatial coverage | keywords | collection       | state     |
      | Document omega | Document      | Document short delta | A document consists of epsilon strings. | Luxembourg       | Alphabet | Collection alpha | validated |
