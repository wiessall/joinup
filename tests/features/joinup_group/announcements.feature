@api
Feature: Messaging group announcements

  Scenario Outline: Announcement creation and transitions

    Given the following <group>s:
      | title        | state     |
      | Fail pension | validated |
      | Hidden gems  | draft     |
    And users:
      | Username             | E-mail                           | Roles     |
      | Sons of tweets       | sonsoftweets@example.com         |           |
      | Check edit moderator | check.edit.moderator@example.com | moderator |
      | Mogul                | mogul@example.com                |           |
      | Greedy               | greedy@example.com               |           |
      | Envious              | envy@example.com                 |           |
      | Coward               | coward@example.com               |           |
      | Nice                 | nice@example.com                 |           |
    And the following <group> user membership:
      | <group>      | user           | roles       |
      | Fail pension | Sons of tweets | facilitator |
      | Hidden gems  | Sons of tweets | facilitator |
      | Fail pension | Mogul          |             |
      | Fail pension | Greedy         |             |
      | Fail pension | Envious        |             |
      | Fail pension | Coward         |             |
    And the following legal document version:
      | Document     | Label | Published | Acceptance label                                                                                   | Content                                                    |
      | Legal notice | 1.1   | yes       | I have read and accept the <a href="[entity_legal_document:url]">[entity_legal_document:label]</a> | The information on this site is subject to a disclaimer... |
    And the following "Legal notice" acceptances:
      | Check edit moderator |
      | Sons of tweets       |
      | Mogul                |
      | Greedy               |
      | Envious              |
      | Nice                 |

    Given I am an anonymous user
    When I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404
    When I go to the announcements page of "Hidden gems" <group>
    Then the response status code should be 404

    Given I am logged in as "Nice"
    When I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404
    When I go to the announcements page of "Hidden gems" <group>
    Then the response status code should be 404

    Given I am logged in as "Sons of tweets"

    When I go to the announcements page of "Hidden gems" <group>
    Then the response status code should be 404

    When I go to the announcements page of "Fail pension" <group>
    And I click "Add announcement"
    Then I should see "Announcement not saved yet"
    # On announcement creation, the state buttons are hidden.
    And I should not see the button "Approve"
    And I should not see the button "Send"
    And I should not see the button "Reject"
    And I should not see the button "Undo reject"
    And I should not see the button "Test"
    And I fill in "Subject" with "The Big Opening"
    And I fill in "Body" with "Lorem Ipsum is simply dummy text of the printing and typesetting industry."

    When I press "Create"
    Then I should see the success message "The announcement has been created as Draft. Review the announcement and, when is ready to be sent, use the 'Send' button."
    And the "Subject" field should contain "The Big Opening"
    And the "Body" field should contain "Lorem Ipsum is simply dummy text of the printing and typesetting industry."
    And I should see "Announcement in Draft state"
    And I should see the button "Send"
    And I should see the button "Test"
    But I should not see the button "Approve"
    And I should not see the button "Reject"
    And I should not see the button "Create"
    And I should not see the button "Undo reject"

    When I fill in "Subject" with "The Big Opening is postponed"
    And I fill in "Body" with "I am @author:name, you can find me at @author:profile:url. Sent from @group:title, link '@group:landing_page:url"
    And I press "Test"
    Then I should see the success message "A sample announcement has been delivered to sonsoftweets@example.com."
    And the email sent to "Sons of tweets" with subject "The Big Opening is postponed" contains the following lines of text:
      | text                                                                                                                                                                                                                                                             |
      | Dear Sons of tweets,                                                                                                                                                                                                                                             |
      | Fail pension <group> has an announcement for its members:                                                                                                                                                                                                     |
      | The Big Opening is postponed                                                                                                                                                                                                                                     |
      | I am Sons of tweets, you can find me at                                                                                                                                                                                                                          |
      | Sent from Fail pension, link                                                                                                                                                                                                                                     |
      | Kind regards,                                                                                                                                                                                                                                                    |
      | The Fail pension <group> team                                                                                                                                                                                                                                 |
      | You received this e-mail because, as a registered user of Joinup and a member of Fail pension <group>, you agreed to receive information sent to you by the Fail pension <group> owner/administrator (based on Article 5(d) of Regulation (EU) 2018/1725). |
      | In case you do not want to receive such e-mails, you can unsubscribe from group announcement notifications or manage all your subscription notifications from the user subscriptions page.                                                                       |
      | This is an unmonitored email address, please do not reply. You can contact directly Fail pension <group> via the About page.                                                                                                                                  |
    When I press "Update"
    Then I should see the success message "The announcement has been updated but it's still in Draft state. Review the announcement and, when is ready to be sent, use the 'Send' button."
    And I should see "Announcement in Draft state"

    When I press "Send"
    Then I should see the success message "Your announcement has been sent for validation by Joinup moderators. Once approved, will reach the Fail pension <group> members."
    And I should see the link "Add announcement"
    # The facilitator has exhausted all operations they can perform, so they
    # cannot edit the announcement anymore.
    And I should not see "edit" in the "The Big Opening is postponed" row
    And the email sent to "Check edit moderator" with subject "Joinup: A new announcement 'The Big Opening is postponed' has been created" contains the following lines of text:
      | text                                                                                                               |
      | A new announcement 'The Big Opening is postponed' has been created by Sons of tweets for the Fail pension <group>. |
      | You can approve or reject the announcement at                                                                      |

    Given I am logged in as "Check edit moderator"

    When I go to the announcements page of "Hidden gems" <group>
    Then the response status code should be 404
    And I click the link from email sent to "Check edit moderator" with subject "Joinup: A new announcement 'The Big Opening is postponed' has been created"
    Then I should see "Announcement in Sent state"
    And I should see the button "Update"
    And I should see the button "Approve"
    And I should see the button "Test"
    But I should not see the button "Create"
    And I should not see the button "Send"
    And I should not see the button "Undo reject"

    When I press "Reject"
    Then I should see the success message "The announcement has been rejected. Press the 'Undo reject' button to restore the announcement."
    Then I should see "Announcement in Rejected state"
    And I should see the button "Update"
    And I should see the button "Test"
    But I should not see the button "Approve"
    And I should not see the button "Create"
    And I should not see the button "Send"
    And I should not see the button "Reject"

    When I press "Test"
    Then I should see the success message "A sample announcement has been delivered to check.edit.moderator@example.com."
    And the email sent to "Check edit moderator" with subject "The Big Opening is postponed" contains the following lines of text:
      | text                                    |
      | Dear Check edit moderator,                                                                                                                                                                                                                                             |
      | Fail pension <group> has an announcement for its members:                                                                                                                                                                                                     |
      | The Big Opening is postponed                                                                                                                                                                                                                                     |
      | I am Sons of tweets, you can find me at                                                                                                                                                                                                                          |
      | Sent from Fail pension, link                                                                                                                                                                                                                                     |
      | Kind regards,                                                                                                                                                                                                                                                    |
      | The Fail pension <group> team                                                                                                                                                                                                                                 |
      | You received this e-mail because, as a registered user of Joinup and a member of Fail pension <group>, you agreed to receive information sent to you by the Fail pension <group> owner/administrator (based on Article 5(d) of Regulation (EU) 2018/1725). |
      | In case you do not want to receive such e-mails, you can unsubscribe from group announcement notifications or manage all your subscription notifications from the user subscriptions page.                                                                       |
      | This is an unmonitored email address, please do not reply. You can contact directly Fail pension <group> via the About page.                                                                                                                                  |

    Given I am logged in as "Sons of tweets"

    When I go the "The Big Opening is postponed" announcement of "Fail pension" <group>
    Then the response status code should be 403

    Given I am logged in as "Check edit moderator"

    When I go the "The Big Opening is postponed" announcement of "Fail pension" <group>
    And  I press "Undo reject"
    Then I should see the success message "The announcement has been restored."
    Then I should see "Announcement in Sent state"
    And I should see the button "Update"
    And I should see the button "Approve"
    And I should see the button "Test"
    But I should not see the button "Create"
    And I should not see the button "Send"
    And I should not see the button "Undo reject"

    Given I am logged in as "Sons of tweets"

    When I go the "The Big Opening is postponed" announcement of "Fail pension" <group>
    Then the response status code should be 403

    Given the "Mogul" user unsubscribes from "Fail pension" <group> announcements
    And the "Greedy" user unsubscribes from "Fail pension" <group> announcements
    And the "Envious" user unsubscribes from "Fail pension" <group> announcements
    And the "Sons of tweets" user unsubscribes from "Fail pension" <group> announcements

    Given I am logged in as "Check edit moderator"

    When I go the "The Big Opening is postponed" announcement of "Fail pension" <group>
    And I press "Approve"
    # Even 'Coward' is still subscribed, he didn't accept yet the legal notice.
    Then I should see the error message "Cannot send this announcement to Fail pension <group> because none of its members are subscribed to receive announcements."

    Given the "Mogul" user subscribes to "Fail pension" <group> announcements
    And the "Greedy" user subscribes to "Fail pension" <group> announcements
    And the "Coward" user subscribes to "Fail pension" <group> announcements
    And the "Sons of tweets" user subscribes to "Fail pension" <group> announcements

    And I press "Approve"
    Then I should see the success message "The announcement has been delivered to the Fail pension <group> members."

    # Groups having up to 50 members are receiving the announcements instantly.
    And the email sent to "Mogul" with subject "The Big Opening is postponed" contains the following lines of text:
      | text                                    |
      | Dear Mogul,                                                                                                                                                                                                                                             |
      | Fail pension <group> has an announcement for its members:                                                                                                                                                                                                     |
      | The Big Opening is postponed                                                                                                                                                                                                                                     |
      | I am Sons of tweets, you can find me at                                                                                                                                                                                                                          |
      | Sent from Fail pension, link                                                                                                                                                                                                                                     |
      | Kind regards,                                                                                                                                                                                                                                                    |
      | The Fail pension <group> team                                                                                                                                                                                                                                 |
      | You received this e-mail because, as a registered user of Joinup and a member of Fail pension <group>, you agreed to receive information sent to you by the Fail pension <group> owner/administrator (based on Article 5(d) of Regulation (EU) 2018/1725). |
      | In case you do not want to receive such e-mails, you can unsubscribe from group announcement notifications or manage all your subscription notifications from the user subscriptions page.                                                                       |
      | This is an unmonitored email address, please do not reply. You can contact directly Fail pension <group> via the About page.                                                                                                                                  |
    And the email sent to "Greedy" with subject "The Big Opening is postponed" contains the following lines of text:
      | text                                    |
      | Dear Greedy,                                                                                                                                                                                                                                             |
      | Fail pension <group> has an announcement for its members:                                                                                                                                                                                                     |
      | The Big Opening is postponed                                                                                                                                                                                                                                     |
      | I am Sons of tweets, you can find me at                                                                                                                                                                                                                          |
      | Sent from Fail pension, link                                                                                                                                                                                                                                     |
      | Kind regards,                                                                                                                                                                                                                                                    |
      | The Fail pension <group> team                                                                                                                                                                                                                                 |
      | You received this e-mail because, as a registered user of Joinup and a member of Fail pension <group>, you agreed to receive information sent to you by the Fail pension <group> owner/administrator (based on Article 5(d) of Regulation (EU) 2018/1725). |
      | In case you do not want to receive such e-mails, you can unsubscribe from group announcement notifications or manage all your subscription notifications from the user subscriptions page.                                                                       |
      | This is an unmonitored email address, please do not reply. You can contact directly Fail pension <group> via the About page.                                                                                                                                  |
    And the email sent to "Sons of tweets" with subject "The Big Opening is postponed" contains the following lines of text:
      | text                                    |
      | Dear Sons of tweets,                                                                                                                                                                                                                                             |
      | Fail pension <group> has an announcement for its members:                                                                                                                                                                                                     |
      | The Big Opening is postponed                                                                                                                                                                                                                                     |
      | I am Sons of tweets, you can find me at                                                                                                                                                                                                                          |
      | Sent from Fail pension, link                                                                                                                                                                                                                                     |
      | Kind regards,                                                                                                                                                                                                                                                    |
      | The Fail pension <group> team                                                                                                                                                                                                                                 |
      | You received this e-mail because, as a registered user of Joinup and a member of Fail pension <group>, you agreed to receive information sent to you by the Fail pension <group> owner/administrator (based on Article 5(d) of Regulation (EU) 2018/1725). |
      | In case you do not want to receive such e-mails, you can unsubscribe from group announcement notifications or manage all your subscription notifications from the user subscriptions page.                                                                       |
      | This is an unmonitored email address, please do not reply. You can contact directly Fail pension <group> via the About page.                                                                                                                                  |
    # Accepted 'Legal notice' but it's not subscribed to group announcements.
    But no email has been sent to "Envious"
    # Subscribed to group announcements but didn't accept the 'Legal notice'.
    And no email has been sent to "Coward"

    And I should see the link "Add announcement"
    But I should not see "edit" in the "The Big Opening is postponed" row

    # Check email links.
    When I click the "Fail pension" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the heading "Fail pension"
    When I click the "unsubscribe" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the heading "Unsubscribe from Fail pension <group>?"
    When I click the "user subscriptions" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the heading "Subscription settings"

    Given I am logged in as "Sons of tweets"

    When I go the "The Big Opening is postponed" announcement of "Fail pension" <group>
    Then the response status code should be 403

    Given I am logged in as "Check edit moderator"

    # Switch off notifications for this group.
    When I go to the homepage of the "Fail pension" <group>
    And I click "Settings"
    And I uncheck the box "Announcements enabled"
    And I press "Save"

    When I go the "The Big Opening is postponed" announcement of "Fail pension" <group>
    Then the response status code should be 403
    But I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404

    Given I am logged in as "Sons of tweets"

    When I go the "The Big Opening is postponed" announcement of "Fail pension" <group>
    Then the response status code should be 403
    But I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404

    Given I am logged in as "Nice"

    When I go the "The Big Opening is postponed" announcement of "Fail pension" <group>
    Then the response status code should be 403
    But I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404

    Given I am an anonymous user

    When I go the "The Big Opening is postponed" announcement of "Fail pension" <group>
    Then I should see the heading "Sign in to continue"
    But I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404

    # Explicitly delete announcements created via UI.
    And I delete the "The Big Opening is postponed" announcement

    Examples:
      | group      |
      | collection |
      | solution   |

  Scenario: Announcement moderation
    Given the following collections:
      | title       | state     |
      | Collection1 | validated |
      | Collection2 | validated |
    And the following solutions:
      | title                 | collection  | state     |
      | Collection1 Solution1 | Collection1 | validated |
      | Collection1 Solution2 | Collection1 | validated |
      | Collection2 Solution1 | Collection2 | validated |
    And users:
      | Username         | E-mail                      | Roles     |
      | Rian Lee         | rian-lee@example.com        |           |
      | Miau user        | miau.user@example.com       |           |
      | Lory Facilitator | loryfacilitator@example.com |           |
      | Xela moderator   | xela.moderator@example.com  | moderator |
    And the following announcements:
      | group                 | subject            | state     | author           | created    |
      | Collection1           | Announcement1 C1   | draft     | Lory Facilitator | 2005-11-05 |
      | Collection1 Solution1 | Announcement1 C1S1 | rejected  | Lory Facilitator | 2018-12-19 |
      | Collection1 Solution1 | Announcement2 C1S1 | sent      | Lory Facilitator | 2018-05-25 |
      | Collection1 Solution2 | Announcement1 C1S2 | draft     | Lory Facilitator | 1999-04-30 |
      | Collection2           | Announcement1 C2   | sent      | Miau user        | 2022-07-29 |
      | Collection2 Solution1 | Announcement1 C2S1 | delivered | Miau user        | 2022-08-03 |
    And the following collection user membership:
      | collection            | user             | roles       |
      | Collection1           | Lory Facilitator | facilitator |
      | Collection2           | Miau user        | facilitator |
      | Collection1 Solution1 | Miau user        |             |
    And the following legal document version:
      | Document     | Label | Published | Acceptance label                                                                                   | Content                                                    |
      | Legal notice | 1.1   | yes       | I have read and accept the <a href="[entity_legal_document:url]">[entity_legal_document:label]</a> | The information on this site is subject to a disclaimer... |
    And the following "Legal notice" acceptances:
      | Rian Lee         |
      | Miau user        |
      | Lory Facilitator |
      | Xela moderator   |

    Given I am logged in as "Xela moderator"
    When I click "Announcements" in the "Administration toolbar"

    Then the "Group" field should contain the "- Any -,Collection1 (Collection),Collection2 (Collection),Collection1 Solution1 (Solution),Collection1 Solution2 (Solution),Collection2 Solution1 (Solution)" options
    And the "State" field should contain the "- Any -,Draft,Sent,Delivered,Rejected" options
    And the "announcement list" table should be:
      | Group                 | Subject            | State     | Created on | Author           | Edit |
      | Collection2 Solution1 | Announcement1 C2S1 | Delivered | 03/08/2022 | Miau user        |      |
      | Collection2           | Announcement1 C2   | Sent      | 29/07/2022 | Miau user        | edit |
      | Collection1 Solution1 | Announcement1 C1S1 | Rejected  | 19/12/2018 | Lory Facilitator | edit |
      | Collection1 Solution1 | Announcement2 C1S1 | Sent      | 25/05/2018 | Lory Facilitator | edit |
      | Collection1           | Announcement1 C1   | Draft     | 05/11/2005 | Lory Facilitator | edit |
      | Collection1 Solution2 | Announcement1 C1S2 | Draft     | 30/04/1999 | Lory Facilitator | edit |

    When I select "Collection1 Solution1 (Solution)" from "Group"
    And I press "Apply"
    Then the "announcement list" table should be:
      | Group                 | Subject            | State    | Created on | Author           | Edit |
      | Collection1 Solution1 | Announcement1 C1S1 | Rejected | 19/12/2018 | Lory Facilitator | edit |
      | Collection1 Solution1 | Announcement2 C1S1 | Sent     | 25/05/2018 | Lory Facilitator | edit |

    When I select "Sent" from "State"
    And I press "Apply"
    Then the "announcement list" table should be:
      | Group                 | Subject            | State | Created on | Author           | Edit |
      | Collection1 Solution1 | Announcement2 C1S1 | Sent  | 25/05/2018 | Lory Facilitator | edit |

    When I click "edit" in the "Announcement2 C1S1" row
    And I press "Approve"
    Then I should see the success message "The announcement has been delivered to the Collection1 Solution1 solution members."

    When I click "Announcements" in the "Administration toolbar"
    Then the "announcement list" table should be:
      | Group                 | Subject            | State     | Created on | Author           | Edit |
      | Collection2 Solution1 | Announcement1 C2S1 | Delivered | 03/08/2022 | Miau user        |      |
      | Collection2           | Announcement1 C2   | Sent      | 29/07/2022 | Miau user        | edit |
      | Collection1 Solution1 | Announcement1 C1S1 | Rejected  | 19/12/2018 | Lory Facilitator | edit |
      # Once delivered, the announcement cannot be edited.
      | Collection1 Solution1 | Announcement2 C1S1 | Delivered | 25/05/2018 | Lory Facilitator |      |
      | Collection1           | Announcement1 C1   | Draft     | 05/11/2005 | Lory Facilitator | edit |
      | Collection1 Solution2 | Announcement1 C1S2 | Draft     | 30/04/1999 | Lory Facilitator | edit |

  @group-clone
  Scenario: Sending mass-mailing announcements.
    Given I am logged in as a moderator
    When I go to the announcements page of "Open Source Observatory (OSOR)" collection
    And I click "Add announcement"
    And I fill in "Subject" with "TEST: Mass-mailing announcement"
    And I fill in "Body" with "This will gonna reach tens of thousands of Open Source Observatory (OSOR) group members."
    And I press "Create"
    Then I should see the success message "The announcement has been created as Draft. Review the announcement and, when is ready to be sent, use the 'Send' button."

    When I press "Send"
    Then I should see the success message "Your announcement has been sent for validation by Joinup moderators. Once approved, will reach the Open Source Observatory (OSOR) collection members."
    When I click "edit" in the "TEST: Mass-mailing announcement" row
    And I press "Approve"
    Then I should see the success message "The announcement has been scheduled to be delivered to the Open Source Observatory (OSOR) collection members. Because the collection has a large amount of members the emails will be sent in several batches."

    Given the delivery queue of "TEST: Mass-mailing announcement" announcement is processed
    Then the "TEST: Mass-mailing announcement" announcement should have been delivered for each group member

    # Explicitly delete announcements created via UI.
    And I delete the "TEST: Mass-mailing announcement" announcement
