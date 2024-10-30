@api @group-d
Feature: "Custom page" editing.
  In order to manage custom pages
  As a moderator or group facilitator
  I need to be able to edit "Custom Page" content through UI.

  Scenario: User should not be allowed unarchiving content of an archived solution.
    Given solution content:
      | title             | state    |
      | Cartridge cleaner | archived |
    And "custom_page" content:
      | title          | solution          | state    |
      | Cyclone filter | Cartridge cleaner | archived |

    When I am logged in as a moderator
    And I go to the edit form of the "Cyclone filter" "custom page"
    Then I should not see the button "Unarchive"

  Scenario Outline: Privileged users can edit custom pages.
    Given users:
      | Username     | E-mail                   |
      | Mickey Mouse | mickey.mouse@example.com |
      | Pluto        | pluto@example.com        |
    And <group> content:
      | title            | state           |
      | Dumbo Collective |published        |
    And the following <group> user memberships:
      | <group>          | user         | roles       |
      | Dumbo Collective | Mickey Mouse | facilitator |
      | Dumbo Collective | Pluto        | member      |
    And "custom_page" content:
      | title                            | <group>          |abstract | body | logo     | state     |
      | Buena Vista Distribution Company | Dumbo Collective | N/A     | N/A  | logo.png | published |

    # Group owner should see the button.
    When I am logged in as "Mickey Mouse"
    And I go to the "Buena Vista Distribution Company" custom page
    Then I should see the link "Edit"
    # A normal member cannot edit custom pages, so they don't see the button.
    When I am logged in as "Pluto"
    And I go to the "Buena Vista Distribution Company" custom page
    Then I should not see the link "Edit"
    # A moderator can edit all custom pages.
    When I am logged in as a user with the moderator role
    And I go to the "Buena Vista Distribution Company" custom page
    Then I should see the link "Edit"
    When I click "Edit"
    Then I should see the heading "Edit Custom page Buena Vista Distribution Company"
    And the following fields should be present "Title, Body, Topic"
    And the following fields should not be present "Groups audience, Other groups, Generate automatic URL alias, URL alias, Published"
    And I should not see the following lines of text:
      | Authored on                                  |
      | Create new revision                          |
      | URL alias (Automatic alias)                  |
      | Generate automatic URL alias                 |
      | Uncheck this to create a custom alias below. |
      | Groups audience                              |
      | Other groups                                 |
      | Promoted to front page                       |
      | Revision information                         |
      | Revision log message                         |
      | Sticky at top of lists                       |

    # A normal logged in user should not be able to edit the custom page.
    When I am logged in as a user with the authenticated role
    And I go to the "Buena Vista Distribution Company" custom page
    Then I should not see the link "Edit"

    # An anonymous user cannot edit the custom page.
    When I am logged in as a user with the authenticated role
    And I go to the "Buena Vista Distribution Company" custom page
    Then I should not see the link "Edit"

    # Edit the page as a facilitator.
    When I am logged in as "Mickey Mouse"
    And I go to the "Buena Vista Distribution Company" custom page
    And I click "Edit"
    Then I should see the heading "Edit Custom page Buena Vista Distribution Company"
    And the following fields should be present "Title, Body, Topic"
    And the following fields should not be present "Groups audience, Other groups, URL alias, Published"
    And the following fields should not be present "Groups audience, Other groups, Generate automatic URL alias, URL alias"
    And I should not see the following lines of text:
      | Authored on                                  |
      | Create new revision                          |
      | URL alias (Automatic alias)                  |
      | Generate automatic URL alias                 |
      | Uncheck this to create a custom alias below. |
      | Groups audience                              |
      | Other groups                                 |
      | Promoted to front page                       |
      | Revision information                         |
      | Revision log message                         |
      | Sticky at top of lists                       |

    When I fill in "Title" with "Walt Disney Studios Motion Pictures"
    And I press "Update"
    Then I should have a "Custom page" content page titled "Walt Disney Studios Motion Pictures"

    Examples:
      | group      |
      | collection |
      | solution   |
