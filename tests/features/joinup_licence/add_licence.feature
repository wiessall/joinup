@api @group-f
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
      | error messages                 |
      | Title field is required.       |
      | Description field is required. |
      | Type field is required.        |

    When I fill in "Title" with "GNU GPL 2+"
    And I fill in "Description" with "The classic open source licence without copyleft."
    # Ensure that the Type field is a dropdown.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3342
    And I select "Public domain" from "Type"
    And I select "GNU General Public License v2.0 or later" from "Corresponding SPDX licence"
    And I select "Strong Community" from "Licence legal type"
    And I additionally select "Venue fixed" from "Licence legal type"
    And I press "Save"
    Then I should have 1 licence
    And I should see the heading "GNU GPL 2+"
    And I should see the text "The classic open source licence without copyleft."
    And I should see the text "Type"
    And I should see the text "Public domain"
    And I should see the text "Corresponding SPDX licence"
    And I should see the text "GNU General Public License v2.0 or later"
    And I should see the text "Licence legal type"
    And I should see the text "Strong Community"
    And I should see the text "Venue fixed"
    And I should see the text "Licence ID"
    And I should see the text "GPL-2.0-or-later"
    And I should see the text "See also"
    And I should see the link "https://opensource.org/licenses/GPL-2.0"
    # Check that the licence text is displayed as paragraphs, and not as a wall
    # of text.
    And I should see a paragraph containing the text "GNU GENERAL PUBLIC LICENSE"
    And I should see a paragraph containing the text "Copyright (C) 1989, 1991 Free Software Foundation, Inc."
    And I should see a paragraph containing the text "Everyone is permitted to copy and distribute verbatim copies of this license document, but changing it is not allowed."

    # Clean up the licence that was created through the UI.
    Then I delete the "GNU GPL 2+" licence

    Examples:
      | role            |
      | moderator       |
      | licence_manager |
