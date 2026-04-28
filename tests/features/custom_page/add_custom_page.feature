@api @group-c
Feature: "Add custom page" visibility options.
  In order to manage custom pages
  As a group facilitator
  I need to be able to add "Custom page" content through UI.

  Background:
    Given collection content:
      | title           | logo     | topic      |
      | Open Collective | logo.png | Demography |
      | Code Camp       | logo.png | Demography |
    Given solution content:
      | title     | logo     | topic      |
      | Parachute | logo.png | Demography |
      | Skydiving | logo.png | Demography |

  Scenario Outline: Links and help text for adding custom pages should should only be shown to privileged users
    # Custom pages cannot be added by normal members. Custom pages are
    # considered to be important, and are not considered 'community content'.
    # Note: Solutions don't have simple members so this check will never really occur for solutions.
    When I am logged in as a member of the "<title>" <group>
    And I go to the homepage of the "<title>" <group>
    Then I should not see the link "Add custom page"
    And I should not see the link "Add a new page" in the "Header"
    And I should not see the text "There are no pages yet. Why don't you start by creating an About page?"
    # If the normal member is promoted to facilitator, the links and help text
    # should become visible.
    Given my role in the "<title>" "<group>" changes to facilitator
    And I reload the page
    Then I should see the link "Add custom page" in the "Plus button menu"
    And I should see the contextual link "Add new page" in the "Header" region

    # An authenticated user which is not a member should also not see the links
    # and help text.
    When I am logged in as an "authenticated user"
    And I go to the homepage of the "<title>" <group>
    Then I should not see the plus button menu
    And I should not see the link "Add a new page" in the "Header"
    And I should not see the text "There are no pages yet. Why don't you start by creating an About page?"

    # An anonymous user should also not see the links and help text.
    When I am an anonymous user
    And I go to the homepage of the "<title>" <group>
    Then I should not see the plus button menu
    And I should not see the link "Add a new page" in the "Header"
    And I should not see the text "There are no pages yet. Why don't you start by creating an About page?"

    # A facilitator should see it.
    When I am logged in as a facilitator of the "<title>" <group>
    And I go to the homepage of the "<title>" <group>
    Then I should see the link "Add custom page" in the "Plus button menu"
    And I should see the contextual link "Add new page" in the "Header" region

    Examples:
      | title     | group      |
      | Code Camp | collection |
      | Parachute | solution   |

  Scenario Outline: Add custom page as a facilitator.
    And I am logged in as a facilitator of the "<second title>" <group>

    # Initially there are no custom pages. A help text should inform the user
    # that it is possible to add custom pages.
    When I go to the homepage of the "<second title>" <group>
    Then the "<second title>" <group> should have 0 custom pages
    And I should see the contextual link "Add new page" in the "Header" region
    When I click the contextual link "Add new page" in the "Header" region
    Then I should see the heading "Add custom page"
    And the following fields should be present "Title, Body"

    # The sections about managing revisions and groups should not be visible.
    And I should not see the text "Revision information"
    And the following fields should not be present "Groups audience, Other groups, Create new revision, Revision log message, Published"

    When I fill in the following:
      | Title    | About us          |
      | Abstract | About us abstract |
    And I attach the file "test.zip" to "Add a new file"
    And I press "Upload"
    # The "Description" field is the description of the file.
    And I fill in "Description" with "Test file"
    And I attach the file "alan.jpg" to "Add a new file"
    And I press "Upload"
    And I fill in the 2nd "Description" field of the "Attachments" file field with "Alan"
    And I uncheck the 2nd "Display" checkbox of the "Attachments" file field
    Then I should not see the button "Archive"
    And I should not see the button "Delete"

    And I press "Save as draft"
    Then I should see the inline form error message "Topic field is required."

    And I select "E-health,EU and European Policies" options from "Topic"
    And I press "Save as draft"
    And I see h1 span element with title "About us"
    And I should see the success message "Custom page About us has been created."
    And I should see the text "Attachments"
    # The description of the file is set as the text to display.
    And I should see the link "Test file"
    But I should not see the link "Alan"
    And I should not see the link "E-health"
    And I should not see the link "EU and European Policies"
    And the "<second title>" <group> should have a custom page titled "About us"
    # Check that the link to the custom page is visible on the group page.
    When I go to the homepage of the "<second title>" <group>
    And I click "About us" in the "Header"

    # I should not be able to add a custom page to a different <group>
    When I go to the homepage of the "<first title>" <group>
    Then I should not see the link "Add custom page"

    When I am an anonymous user
    And I go to the homepage of the "<second title>" <group>
    Then I should not see the link "About us" in the "Header"

    Examples:
      | first title | second title    | group      |
      | Code Camp   | Open Collective | collection |
      | Parachute   | Skydiving       | solution   |

  Scenario: Title can be visually hidden.
    Given collection content:
      | title             |
      | Aggressive Rubber |
    And custom_page content:
      | title          | collection        | field_hide_title |
      | Rubber's bands | Aggressive Rubber | 1                |

    When I go to the "Rubber's bands" custom_page
    Then I see the text "Rubber's bands" in the "Visually hidden title" region

  Scenario: Moderators can access the "Add custom page" menu item.
    Given users:
      | Username | Roles     |
      | Falstad  | moderator |

    Given I am logged in as "Falstad"
    When I go to the homepage of the "Open Collective" collection
    Then I should see the link "Add custom page" in the "Plus button menu"
    When I go to the homepage of the "Code Camp" collection
    Then I should see the link "Add custom page" in the "Plus button menu"
    When I go to the homepage of the "Parachute" solution
    Then I should see the link "Add custom page" in the "Plus button menu"
    When I go to the homepage of the "Skydiving" solution
    Then I should see the link "Add custom page" in the "Plus button menu"

  @javascript
  Scenario: Long list of attachments should be collapsed.
    Given collection content:
      | title             |
      | Aggressive Rubber |
    # Create custom pages with 5 and 6 attachments.
    # 5 is the limit before adding the "Show more" functionality.
    And custom_page content:
      | title          | body                 | collection        | attachments                                                                                                                                        |
      | Rubber bands   | The aggressive ones. | Aggressive Rubber | empty.rdf - display: 1 - description: empty.rdf, empty_pdf.pdf - display: 1 - description: empty_pdf.pdf, invalid_adms.rdf - display: 1 - description: invalid_adms.rdf, test.zip - display: 1 - description: test.zip, text.pdf - display: 1 - description: text.pdf                                                |
      | Elastic rubber | Also aggressive.     | Aggressive Rubber | ada.png - display: 1 - description: ada.png, alan.jpg - display: 1 - description: alan.jpg, blaise.jpg - display: 1 - description: blaise.jpg, charles.jpg - display: 1 - description: charles.jpg, leonardo.jpg - display: 1 - description: leonardo.jpg, linus.jpeg - display: 1 - description: linus.jpeg |

    When I go to the "Rubber bands" custom_page
    Then the "empty.rdf" link in the Content region should be visible
    And the "empty_pdf.pdf" link in the Content region should be visible
    And the "invalid_adms.rdf" link in the Content region should be visible
    And the "test.zip" link in the Content region should be visible
    And the "text.pdf" link in the Content region should be visible
    But I should not see the button "Show more" in the Content region

    When I go to the "Elastic rubber" custom_page
    Then the "ada.png" link in the Content region should be visible
    And the "alan.jpg" link in the Content region should be visible
    And the "blaise.jpg" link in the Content region should be visible
    And the "charles.jpg" link in the Content region should be visible
    And the "leonardo.jpg" link in the Content region should be visible
    And the "Show more" button in the Content region should be visible
    # The sixth element should be hidden.
    But the "linus.jpeg" link in the Content region should not be visible

    # Expand the list. The sixth element should become visible.
    When I press "Show more"
    And I wait for CSS animations to finish
    Then the "linus.jpeg" link in the Content region should be visible
    And the "Show less" button in the Content region should be visible
    But I should not see the button "Show more" in the Content region

    # Collapse again the list.
    When I press "Show less"
    And I wait for CSS animations to finish
    Then the "linus.jpeg" link in the Content region should not be visible
    And the "Show more" button in the Content region should be visible
    But I should not see the button "Show less" in the Content region

  Scenario Outline: Creating a custom page with a title that already exists.
    And custom_page content:
      | title        | body        | <group>       | creation date       |
      | Rubber boots | For outside | <first title> | 2014-08-30 23:59:00 |

    # It should not be possible to create a custom page with a duplicate title.
    Given I am logged in as a facilitator of the "<first title>" <group>
    When I go to the homepage of the "<first title>" <group>
    And I click the contextual link "Add new page" in the "Header" region
    Then I should see the heading "Add custom page"

    When I fill in the following:
      | Title    | Rubber boots          |
      | Abstract | Rubber boots abstract |
    And I press "Save"
    Then I should see the inline form error message "The Custom page title value (Rubber boots) is already taken by Rubber boots."

    # It should still be possible to create a custom page with the existing
    # title in a different group.
    Given I am logged in as a facilitator of the "<second title>" <group>
    When I go to the homepage of the "<second title>" <group>
    And I click the contextual link "Add new page" in the "Header" region
    Then I should see the heading "Add custom page"

    When I fill in the following:
      | Title    | Rubber boots          |
      | Abstract | Rubber boots abstract |
    And I select "E-health" options from "Topic"
    And I press "Save"
    And I see h1 span element with title "Rubber boots"
    And I should see the success message "Custom page Rubber boots has been created."

    # Assert that the "Custom page" tile is present and contains last update, label and bundle type.
    When I go to "/user"
    Then I should see the "Rubber boots" tile
    And I should see the text "Last update: " in the "Rubber boots" tile
    And I should see the text "Custom page" in the "Rubber boots" tile

    Examples:
      | first title | second title    | group      |
      | Code Camp   | Open Collective | collection |
      | Parachute   | Skydiving       | solution   |

  Scenario Outline: Regression test for auto generated aliases.
    # Regression test for ISAICP-8886.
    Given custom_page content:
      | title   | body        | <type>  | creation date       |
      | <title> | For outside | <group> | 2014-08-30 23:59:00 |

    When I am logged in as a moderator
    And I go to the homepage of the "<group>" <type>
    And I click "<link>" in the plus button menu
    Then the response status code should be 200

    Examples:
      | title        | group     | type       | link             |
      | Distribution | Parachute | solution   | Add distribution |
      | News         | Parachute | solution   | Add news         |
      | Discussion   | Parachute | solution   | Add discussion   |
      | Document     | Parachute | solution   | Add document     |
      | Event        | Parachute | solution   | Add event        |
      | Solution     | Code Camp | collection | Add solution     |
