@api @group-clone
Feature: Add a licence through the UI
  In order to manage licences
  As a moderator or a licence manager
  I need to be able to add licences through the UI.

  Scenario Outline: Add licence as a Moderator or a Licence Manager.
    Given I am logged in as a "<role>"
    And I am on the homepage
    # The dashboard link is visible by clicking the user icon.
    When I click "Dashboard"
    And I click "Licences overview"
    And I click "Add licence"
    Then I should see the heading "Create Licence"
    And the "Licence legal type" field should contain the "Can, Must, Cannot, Compatible, Law, Support" option groups

    When I press "Save"
    Then I should see the following error messages:
      | error messages                    |
      | Title field is required.          |
      | Description field is required.    |
      | Type field is required.           |

    When I fill in "Title" with "The Licence To Rule Them All"
    And I fill in "Description" with "The classic open source licence without copyleft."
    # Ensure that the Type field is a dropdown.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3342
    And I select "Public domain" from "Type"
    And I select "LaTeX Project Public License v1.0" from "Corresponding SPDX licence"
    And I select "Strong Community" from "Licence legal type"
    And I additionally select "Venue fixed" from "Licence legal type"
    And I press "Save"
    And I should see the heading "The Licence To Rule Them All"
    And I should see the text "The classic open source licence without copyleft."
    And I should see the text "Type"
    And I should see the text "Public domain"
    And I should see the text "Corresponding SPDX licence"
    And I should see the text "LaTeX Project Public License v1.0"
    And I should see the text "Licence legal type"
    And I should see the text "Strong Community"
    And I should see the text "Venue fixed"
    And I should see the text "Licence ID"
    And I should see the text "MIT"
    And I should see the text "See also"
    And I should see the link "http://www.latex-project.org/lppl/lppl-1-0.txt"
    # Check that the licence text is displayed as paragraphs, and not as a wall
    # of text.
    And I should see a paragraph containing the text "LaTeX Project Public License"
    And I should see a paragraph containing the text "LPPL Version 1.0 1999-03-01"
    And I should see a paragraph containing the text "Copyright 1999 LaTeX3 Project"
    And I should see a paragraph containing the text "Everyone is permitted to copy"

    # Clean up the licence that was created through the UI.
    Then I delete the "The Licence To Rule Them All" licence

    Examples:
      | role            |
      | moderator       |
      | licence_manager |
