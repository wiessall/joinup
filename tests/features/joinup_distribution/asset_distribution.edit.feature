@api @group-e
Feature: Asset distribution editing.
  In order to manage distributions
  As a solution owner or solution facilitator
  I need to be able to edit distributions through UI.

  Background:
    Given users:
      | Username     | E-mail                   | First name | Family name | Roles |
      | Gregg Hill   | Gregg.Hill@example.com   | Gregg      | Hill        |       |
      | Pedro Torres | Pedro.Torres@example.com | Pedro      | Torres      |       |
    And the following collection:
      | title | Collection example |
      | state | published          |
    And the following solutions:
      | title      | collection         | description        | state     |
      | Solution A | Collection example | Sample description | published |
      | Solution B | Collection example | Sample description | published |
    And licence content:
      | title | description                         | deprecated |
      | LGPL  | The LGPL more permisssive than GPL. | yes        |
    And release content:
      | title                 | release number | release notes       | is version of |
      | Asset release example | C3PO           | Release description | Solution A    |
    And distribution content:
      | title                      | description        | licence | access url | release               | state     | publication date | modification date |
      | Asset distribution example | Sample description | LGPL    | test.zip   | Asset release example | published | 2019-11-19       | 2021-11-19        |
    And the following solution user membership:
      | solution   | user         | roles       |
      | Solution A | Gregg Hill   | owner       |
      | Solution A | Pedro Torres | facilitator |

  Scenario: "Edit" button should be shown to facilitators of the related solution.
    When I am logged in as a facilitator of the "Solution A" solution
    And I go to the homepage of the "Asset distribution example" distribution
    Then I should see the link "Edit" in the "Entity actions" region

    When I am logged in as a facilitator of the "Solution B" solution
    And I go to the homepage of the "Asset distribution example" distribution
    Then I should not see the link "Edit" in the optional "Entity actions" region

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "Asset distribution example" distribution
    Then I should not see the link "Edit" in the optional "Entity actions" region

    When I am an anonymous user
    And I go to the homepage of the "Asset distribution example" distribution
    Then I should not see the link "Edit" in the optional "Entity actions" region
    And I should see the text "Published on: 19/11/2019"
    And I should see the text "Last update: 19/11/2021"

  Scenario: Edit a distribution as a solution facilitator.
    Given licence content:
      | title           | description                         | deprecated |
      | The same LGPL   | The LGPL more permisssive than GPL. | yes        |
      | Another LGPL    | The LGPL more permisssive than GPL. | yes        |
      | Some other LGPL | The LGPL more permisssive than GPL. | yes        |
    When I am logged in as a facilitator of the "Solution A" solution
    And I go to the homepage of the "Asset distribution example" distribution
    And I click "Edit"
    Then I should see the heading "Edit Distribution Asset distribution example"
    And the following fields should not be present "Langcode, Translation"
    But the following fields should be present "Description, Access URL, Licence, Format, Status, Representation technique"
    # Ensure that the current licence is available even if it is deprecated but a warning is shown.
    And the "Licence" field should contain the "LGPL" option
    And the "Licence" select field options should be sorted alphabetically
    And I should see the warning message "The referenced licence LGPL is deprecated. You are advised to chose a different licence."
    When I fill in "Title" with "Asset distribution example revised"
    # Set a non-HTTP protocol remote URL.
    And I press "Remove"
    And I set a remote URL "ftp://example.com/file.txt" to "Access URL"
    And I press "Update"
    Then I should see the heading "Asset distribution example revised"
    And the following email should have been sent:
      | recipient | Gregg Hill                                                                                                                                          |
      | subject   | Portal: A distribution has been updated                                                                                                             |
      | body      | The distribution Asset distribution example revised of the release Asset release example, C3PO of the solution Solution A was successfully updated. |
    And the following email should have been sent:
      | recipient | Pedro Torres                                                                                                                                        |
      | subject   | Portal: A distribution has been updated                                                                                                             |
      | body      | The distribution Asset distribution example revised of the release Asset release example, C3PO of the solution Solution A was successfully updated. |
