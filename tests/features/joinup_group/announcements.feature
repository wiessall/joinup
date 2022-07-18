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
    And I should see the link "Add announcement"
    And I click "Add announcement"
    And I fill in "Subject" with "Test anon [rdf_entity:label]"
    And I fill in "Body" with "Lorem Ipsum is simply dummy text of the printing and typesetting industry."
    And the following field should not be present "State"
    When I press "Submit"
    Then I should see the success message "Your announcement has been submitted for validation."

    And the following email should have been sent:
      | recipient | Check edit moderator                                                  |
      | subject   | Joinup: A new announcement "Test anon Fail pension" has been created. |
      | body      | Sons Tweets has created this announcement "Test anon Fail pension".   |

    # Check "Add announcement" button permissions
    Given I am logged in as "Sons of tweets"
    And I go to the homepage of the "Fail pension" collection
    Then I click "Members"
    And I should see the link "Add announcement"
    And I should see the link "Announcement list"

    When I am logged in as "Check edit moderator"
    And I go to the homepage of the "Fail pension" collection
    Then I click "Members"
    And I should see the link "Add announcement"
    And I should see the link "Announcement list"

    When I am an anonymous user
    And I go to the homepage of the "Fail pension" collection
    Then I click "Members"
    And I should not see the link "Add announcement"
    And I should not see the link "Announcement list"

    When I am logged in as a user with the "authenticated" role
    And I go to the homepage of the "Fail pension" collection
    Then I click "Members"
    And I should not see the link "Add announcement"
    And I should not see the link "Announcement list"

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
    And I should not see the link "Add announcement"
    And I should not see the link "Announcement list"

    When I am logged in as "Rony Facilitator"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should see the link "Add announcement"
    And I should see the link "Announcement list"

    When I am logged in as "Xiau user"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should see the link "Add announcement"
    And I should see the link "Announcement list"

    When I am an anonymous user
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Add announcement"
    And I should not see the link "Announcement list"

    When I am logged in as "Alex moderator"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should see the link "Add announcement"
    And I should see the link "Announcement list"

    # Disable announcements.
    And I go to the homepage of the "Nachos test" <content type>
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    And I uncheck the box "Announcements enabled"
    Then I press "Save"

    Given I am logged in as "Loan Lee"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Add announcement"
    And I should not see the link "Announcement list"

    When I am logged in as "Rony Facilitator"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Add announcement"
    And I should see the link "Announcement list"

    When I am logged in as "Xiau user"
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Add announcement"
    And I should see the link "Announcement list"

    When I am an anonymous user
    And I go to the homepage of the "Nachos test" <content type>
    Then I click "Members"
    And I should not see the link "Add announcement"
    And I should not see the link "Announcement list"

    Examples:
      | content type |
      | collection   |
      | solution     |

  Scenario: Check announcements list
    Given the following collections:
      | title        | state     | closed |
      | Old pension | validated | no     |
      | New pension | validated | no     |
    And users:
      | Username         | E-mail                       | First name  | Family name | Roles     |
      | Rian Lee         | rian-lee@example.com         | Rian        | Lee         |           |
      | Miau user        | miau.user@example.com        | Miau        | user        |           |
      | Lory Facilitator | loryfacilitator@example.com  | Lory        | Facilitator |           |
      | Xela moderator   | xela.moderator@example.com   | Xela        | Mod         | moderator |
    And the following collection user membership:
      | collection   | user             | roles       |
      | Old pension  | Lory Facilitator | facilitator |
      | New pension  | Rian Lee         | owner       |
      | New pension  | Miau user        | facilitator |

    When I am logged in as "Rian Lee"
    And I go to the homepage of the "New pension" collection
    Then I click "Members"
    And I should see the link "Add announcement"
    And I click "Add announcement"
    And I fill in "Subject" with "Live festival [rdf_entity:label]"
    And I fill in "Body" with "Lorem Ipsum is simply dummy text of the printing and typesetting industry."
    And the following field should not be present "State"
    When I press "Submit"
    Then I should see the success message "Your announcement has been submitted for validation."

    When I am logged in as "Lory Facilitator"
    And I go to the homepage of the "Old pension" collection
    Then I click "Members"
    And I should see the link "Add announcement"
    And I click "Add announcement"
    And I fill in "Subject" with "Music pool party"
    And I fill in "Body" with "Party lorem ipsum is simply dummy text of the printing and typesetting industry."
    And the following field should not be present "State"
    When I press "Submit"
    Then I should see the success message "Your announcement has been submitted for validation."

    # Check "Announcement list" button permissions
    Given I am logged in as "Rian Lee"
    And I go to the homepage of the "Old pension" collection
    Then I click "Members"
    And I should not see the link "Announcement list"

    Then I go to the homepage of the "New pension" collection
    Then I click "Members"
    And I should see the link "Announcement list"
    Then I click "Announcement list"
    And I should see a table with 4 columns
    And the "announcement list" table should contain the following column:
      | Subject                  |
      | Live festival New pension|
    # Create a new announcement.
    Then I click "Members"
    And I should see the link "Add announcement"
    And I click "Add announcement"
    And I fill in "Subject" with "The Shining"
    And I fill in "Body" with "The Shining industry."
    And the following field should not be present "State"
    When I press "Submit"
    Then I should see the success message "Your announcement has been submitted for validation."

    When I am logged in as "Miau user"
    And I go to the homepage of the "Old pension" collection
    Then I click "Members"
    And I should not see the link "Announcement list"

    Then I go to the homepage of the "New pension" collection
    Then I click "Members"
    And I should see the link "Announcement list"
    Then I click "Announcement list"
    And the "announcement list" table should contain the following column:
      | Subject                   | Edit  | State |
      | Live festival New pension | edit	| New   |
      | The Shining               | edit	| New   |
    Then I click "edit" on the row containing "Live festival New pension"
    And the following fields should be disabled "Group reference,State"
    And I fill in "Subject" with "Cracking the web."
    And I press "Update"
    Then the "announcement list" table should contain the following column:
      | Subject           | Edit  | State |
      | Cracking the web. | edit	| New   |
      | The Shining       | edit	| New   |

    When I am logged in as "Lory Facilitator"
    And I go to the homepage of the "Old pension" collection
    Then I click "Members"
    And I should see the link "Announcement list"
    Then I click "Announcement list"
    And the "announcement list" table should contain the following column:
      | Subject          | Edit  | State |
      | Music pool party | edit	 | New   |
    Then I click "edit" on the row containing "Music pool party"
    And the following fields should be disabled "Group reference,State"

    Then I go to the homepage of the "New pension" collection
    Then I click "Members"
    And I should not see the link "Announcement list"

    When I am logged in as "Xela moderator"
    And I go to the homepage of the "Old pension" collection
    Then I click "Members"
    And I should see the link "Announcement list"
    Then I click "Announcement list"
    And the "announcement list" table should contain the following column:
      | Subject          | Edit  | State |
      | Music pool party | edit	| New    |

    Then I go to the homepage of the "New pension" collection
    Then I click "Members"
    And I should see the link "Announcement list"
    Then I click "Announcement list"
    And the "announcement list" table should contain the following column:
      | Subject           | Edit  | State |
      |	Cracking the web.	| edit	| New   |
      |	The Shining		    | edit  | New   |

    # Check toolbar announcements view.
    And I click "Announcements" in the "Administration toolbar" region
    Then I click "edit" on the row containing "The Shining"
    And I select "Reject" from "State"
    And I press "Update"
    Then I click "edit" on the row containing "The Shining"
    And I select "Sent" from "State"
    And I press "Update"
    And the "announcement list" table should contain the following column:
      | Group reference | Subject           | Edit  | State |
      | New pension     |	Cracking the web.	| edit	| New   |
      | Old pension     |	Music pool party	| edit	| New   |
      | New pension     |	The Shining		    |       | Sent  |

    When I am an anonymous user
    And I go to the homepage of the "Old pension" collection
    Then I click "Members"
    And I should not see the link "Announcement list"

    Then I go to the homepage of the "New pension" collection
    Then I click "Members"
    And I should not see the link "Announcement list"

    # Check announcement list without edit in "The Shining".
    When I am logged in as "Miau user"
    Then I go to the homepage of the "New pension" collection
    Then I click "Members"
    And I should see the link "Announcement list"
    Then I click "Announcement list"
    And the "announcement list" table should contain the following column:
      | Subject           | Edit  | State |
      |	Cracking the web.	| edit	| New   |
      |	The Shining		    |       | Sent  |
