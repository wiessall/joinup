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

