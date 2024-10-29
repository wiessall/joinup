@api @group-h
Feature:
  - As a moderator, in order to maintain the support dropdown, I am able to administer the 'support' menu.
  - As a user, I should see the the 'Take a tour' menu item only on pages that are implementing tours.
  - As a moderator I can add custom menu items and disable default menu items.

  Scenario Outline: Moderators are not able to admin menus except the support and footer menus.

    Given I am an anonymous user
    When I go to "/admin/structure/menu/manage/<menu>"
    Then I should see the heading "Sign in to continue"

    Given I am logged in as an "authenticated user"
    When I go to "/admin/structure/menu/manage/<menu>"
    Then I should get an access denied error

    Given I am logged in as a moderator
    When I go to "/admin/structure/menu/manage/<menu>"
    Then the response status code should be <code>
    # The moderator cannot edit the menu itself but only the menu links.
    And the following fields should not be present "Title,Administrative summary,Menu language"
    But I should <add link> the link "Add link"

    Examples:
      | menu                       | code | add link |
      | admin                      | 403  | not see  |
      | footer                     | 403  | not see  |
      | main                       | 403  | not see  |
      | support                    | 200  | see      |
      | footer-about-us            | 200  | see      |
      | footer-european-commission | 200  | see      |
      | footer-follow-us           | 200  | see      |
      | footer-help-and-support    | 200  | see      |
      | navbar                     | 200  | see      |
      | tools                      | 403  | not see  |
      | account                    | 403  | not see  |

  Scenario Outline: Test user support menu usage.

    Given the following collections:
      | title            | state     |
      | Hotel California | published |

    Given I am <role>

    When I am on the homepage
    Then I should <homepage expectation> the link "Take a tour" in the "Footer"
    And I should see the link "Contact Portal Support"
    Then I click "Contact Portal Support"
    And I fill in the following:
      | First name     | Rufus                       |
      | Last name      | Modric                      |
      | Organisation   | The Deaf-Mute Society       |
      | E-mail address | oswine@example.ca           |
      | Category       | other                       |
      | Subject        | Mobile reader accessibility |
      | Message        | Dear sir, madam, ...        |
    And I press "Submit"
    Then I should be on the homepage

    When I visit the collection overview page
    Then I should not see the link "Take a tour"
    But I should see the link "Contact Portal Support"
    When I click "Contact Portal Support"
    And I fill in the following:
      | First name     | Oswine                      |
      | Last name      | Wulfric                     |
      | Organisation   | The Deaf-Mute Society       |
      | E-mail address | oswine@example.za           |
      | Category       | other                       |
      | Subject        | Screen reader accessibility |
      | Message        | Dear sir, madam, ...        |
    And I press "Submit"
    Then I should be on "collections"

    When I visit the content overview page
    Then I should see the link "Take a tour"
    And I should see the link "Contact Portal Support"
    When I click "Contact Portal Support"
    And I fill in the following:
      | First name     | Roscof                      |
      | Last name      | Vulvric                     |
      | Organisation   | The Deaf-Mute Society       |
      | E-mail address | roswine@example.za          |
      | Category       | other                       |
      | Subject        | Screen reader disponibility |
      | Message        | Dear sir, madam, ...        |
    And I press "Submit"
    Then I should be on "keep-up-to-date"

    When I go to the homepage of the "Hotel California" collection
    Then I should see the link "Take a tour"
    And I should see the link "Contact Portal Support"

    When I go to "/user"
    Then I should <user page expectation> the link "Take a tour"
    And I should see the link "Contact Portal Support"

    Examples:
      | role                                 | user page expectation | homepage expectation |
      | an anonymous user                    | not see               | see                  |
      | logged in as an "authenticated user" | see                   | not see              |

  Scenario: A moderator is able to administer the "Help and support" menu.
    Given I am logged in as a moderator
    When I click "Menus"
    And I click "List links" in the "Help and support" row
    Then I should see the heading "Edit menu Help and support"

    When I uncheck the "Take a tour" row
    And I press "Save"
    Then I should see the success message "Menu Help and support has been updated."

    And I click "Add link"
    And I fill in "Menu link title" with "Arbitrary support menu link"
    And I fill in "Link" with "http://example.com"
    And I press "Save"
    Then I should see the link "Arbitrary support menu link"

    When I click "Edit" in the "Arbitrary support menu link" row
    Then I should see the heading "Edit menu link"
    And the following fields should be present "Menu link title,Link,Enabled,Description,Show as expanded,Parent link,Weight"
    When I press "Save"
    Then I should see the success message "The menu link has been saved."

    When I click "Edit" in the "Take a tour" row
    Then I should see the heading "Edit menu link Take a tour"
    When I move backward one page
    And I click "Edit" in the "Contact Portal Support" row
    Then I should see the heading "Edit menu link Contact Portal Support"

    Given I am an anonymous user
    And I am on the homepage
    Then I should see the link "Contact Portal Support"
    And I should see "Arbitrary support menu link"
    # Tour has been disabled.
    But I should not see the link "Take a tour"

    Given I am logged in as a moderator
    # Restore the tour menu link.
    When I click "Menus"
    And I click "List links" in the "Help and support" row
    And I check the "Take a tour" row
    And I press "Save"

    # Reset the tour menu link.
    When I click "Reset" in the "Take a tour" row
    Then I should see the heading "Are you sure you want to reset the link Take a tour to its default values?"
    When I press "Reset"
    Then I should not see the link "Reset"

    When I click "Delete" in the "Arbitrary support menu link" row
    Then I should see the heading "Are you sure you want to delete the custom menu link Arbitrary support menu link?"

    When I press "Delete"
    Then I should see the success message "The menu link Arbitrary support menu link has been deleted."
    And I should not see the link "Arbitrary support menu link"

    Given I am an anonymous user
    When I am on the homepage
    Then I should see the link "Contact Portal Support"
    # The custom link has been deleted.
    But I should not see "Arbitrary support menu link"
    # Tour has been re-enabled.
    And I should see the link "Take a tour"
