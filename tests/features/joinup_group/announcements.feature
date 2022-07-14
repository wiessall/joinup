@api
Feature: Messaging group announcements

  Scenario: Create new pending announcement
    Given the following collections:
      | title        | state     | closed |
      | Fail pension | validated | no     |
    And users:
      | Username             | E-mail                           | Roles     | First name | Family name |
      | Sons of tweets       | sonsoftweets@example.com         |           | Sons       | Tweets      |
      | Check edit moderator | check.edit.moderator@example.com | moderator | Ronny      | Ray         |
    And the following collection user membership:
      | collection   | user           | roles       |
      | Fail pension | Sons of tweets | facilitator |

    When I am logged in as "Sons of tweets"
    And I go to the homepage of the "Fail pension" collection
    Then I click "Members"
    And I should see the link "Announcement"
    And I click "Announcement"
    And I fill in "Subject" with "Test anon [rdf_entity:label]"
    And I fill in "Body" with "Lorem Ipsum is simply dummy text of the printing and typesetting industry."
    And the following field should not be present "State"
    When I press "Submit"
    Then I should see the success message "Your announcement has been submitted for validation."

    And the following email should have been sent:
      | recipient | Check edit moderator                                                  |
      | subject   | Joinup: A new announcement "Test anon Fail pension" has been created. |
      | body      | Sons Tweets has created this announcement "Test anon Fail pension".   |

    # Check "Announcement" button permissions
    Given I am logged in as "Sons of tweets"
    And I go to the homepage of the "Fail pension" collection
    Then I click "Members"
    And I should see the link "Announcement"

    When I am logged in as "Check edit moderator"
    And I go to the homepage of the "Fail pension" collection
    Then I click "Members"
    And I should see the link "Announcement"

    When I am an anonymous user
    And I go to the homepage of the "Fail pension" collection
    Then I click "Members"
    And I should not see the link "Announcement"

    When I am logged in as a user with the "authenticated" role
    And I go to the homepage of the "Fail pension" collection
    Then I click "Members"
    And I should not see the link "Announcement"

  Scenario Outline: Check permissions when disable and enable announcements.

    Given the following <content type>:
      | title      | Nachos test   |
      | state      | validated     |
    And users:
      | Username         | E-mail                       | First name  | Family name | Roles     |
      | Loan Lee         | loan-lee@example.com         | Loan        | Lee         |           |
      | Xiau user        | xiau.user@example.com        | Xiau        | user        |           |
      | Rony Facilitator | ronyfacilitator@example.com  | Rony        | Facilitator |           |
      | Alex moderator   | alex.moderator@example.com   | Alex        | Mod         | moderator |
    And the following <content type> user memberships:
      | <content type>  | user             | roles       |
      | Nachos test     | Rony Facilitator | facilitator |
      | Nachos test     | Xiau user        | owner       |

    Given I am logged in as "Loan Lee"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Announcement"

    When I am logged in as "Rony Facilitator"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should see the link "Announcement"

    When I am logged in as "Xiau user"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should see the link "Announcement"

    When I am an anonymous user
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Announcement"

    When I am logged in as "Alex moderator"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should see the link "Announcement"

    # Disable announcements.
    And I go to the homepage of the "Nachos test" <content type>
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    And I uncheck the box "Announcements enabled"
    Then I press "Save"

    Given I am logged in as "Loan Lee"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Announcement"

    When I am logged in as "Rony Facilitator"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Announcement"

    When I am logged in as "Xiau user"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Announcement"

    When I am an anonymous user
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Announcement"

    Examples:
      | content type |
      | collection   |
      | solution     |
