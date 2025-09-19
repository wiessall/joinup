# Contains a scenario with @group-clone annotation.
@api
Feature: Messaging group announcements

  @group-e
  Scenario Outline: Announcement creation and transitions

    Given <group> content:
      | title        | state     |
      | Fail pension | published |
      | Hidden gems  | draft     |
    And users:
      | Username             | E-mail                           | Roles     |
      | Sons of tweets       | sonsoftweets@example.com         |           |
      | Check edit moderator | check.edit.moderator@example.com | moderator |
      | Mogul                | mogul@example.com                |           |
    And the following <group> user membership:
      | <group>      | user           | roles       |
      | Fail pension | Sons of tweets | facilitator |
      | Hidden gems  | Sons of tweets | facilitator |
      | Fail pension | Mogul          |             |
    And the following legal document version:
      | Document     | Label | Published | Acceptance label                                                                                   | Content                                                    |
      | Legal notice | 1.1   | yes       | I have read and accept the <a href="[entity_legal_document:url]">[entity_legal_document:label]</a> | The information on this site is subject to a disclaimer... |
    And the following "Legal notice" acceptances:
      | Check edit moderator |
      | Sons of tweets       |
      | Mogul                |

    Given I am logged in as "Sons of tweets"

    # The 'Add announcement' button is also available from the members page.
    When I go to the members page of "Fail pension"
    And I click "Add announcement"
    Then the following fields should be present "Subject,Body"
    But the following fields should not be present "Professional domain filter"
    And I should see the following lines of text:
      | Select dynamic placeholders for your announcement                                                                                                                                                  |
      | Copy and paste, in the message body, one or more placeholder(s) (e.g. @recipient_name) and the corresponding shortcut link(s) will be displayed in the announcement email. Available placeholders: |
      | @group_landing_page - will display the <group> landing page URL                                                                                                                                    |
      | @group_members_page - will display the <group> members page URL                                                                                                                                    |
      | @recipient_name - will display the recipient's full name                                                                                                                                           |
      | @recipient_profile - will display the recipient's profile URL                                                                                                                                      |
      | @recipient_profile_edit - will display the recipient's profile edit URL                                                                                                                            |
      | @recipient_subscriptions - will display the recipient's subscriptions page URL                                                                                                                     |
      | Announcement state: Not saved                                                                                                                                                                      |

    When I go to the announcements page of "Fail pension" <group>
    And I click "Add announcement"
    Then the following fields should be present "Subject,Body"
    And I should see the following lines of text:
      | Select dynamic placeholders for your announcement                                                                                                                                                  |
      | Copy and paste, in the message body, one or more placeholder(s) (e.g. @recipient_name) and the corresponding shortcut link(s) will be displayed in the announcement email. Available placeholders: |
      | @group_landing_page - will display the <group> landing page URL                                                                                                                                    |
      | @group_members_page - will display the <group> members page URL                                                                                                                                    |
      | @recipient_name - will display the recipient's full name                                                                                                                                           |
      | @recipient_profile - will display the recipient's profile URL                                                                                                                                      |
      | @recipient_profile_edit - will display the recipient's profile edit URL                                                                                                                            |
      | @recipient_subscriptions - will display the recipient's subscriptions page URL                                                                                                                     |
      | Announcement state: Not saved                                                                                                                                                                      |
    # On announcement creation, the state buttons are hidden.
    And I should not see the button "Approve"
    And I should not see the button "Send for approval"
    And I should not see the button "Reject"
    And I should not see the button "Undo reject"
    And I should not see the button "Preview"
    And I fill in "Subject" with "The Big Opening"
    And I fill in "Body" with "Lorem Ipsum is simply dummy text of the printing and typesetting industry."

    When I press "Create"
    Then I should see the success message "The announcement has been created as Draft. Review the announcement and, when is ready to be sent, use the 'Send' button."
    And Log entry with the message "The announcement The Big Opening has been created by Sons of tweets for Fail pension." should exist.
    And the "Subject" field should contain "The Big Opening"
    And the "Body" field should contain "Lorem Ipsum is simply dummy text of the printing and typesetting industry."
    And I should see "Announcement state: Draft"
    And I should see the button "Send for approval"
    And I should see the button "Preview"
    But I should not see the button "Approve"
    And I should not see the button "Reject"
    And I should not see the button "Create"
    And I should not see the button "Undo reject"

    # Test subject formatting (special chars, HTML tags).
    When I fill in "Subject" with "Subject with special chars '&>< and HTML <span>tag</span> <Your feedback matters>"
    When I press "Preview"
    And the email sent to "Sons of tweets" with subject "Subject with special chars '&>< and HTML <span>tag</span> <Your feedback matters>" contains the following lines of text:
      | Subject with special chars '&>< and HTML <span>tag</span> <Your feedback matters> |
    And the email sent to "Sons of tweets" with subject "Subject with special chars '&>< and HTML <span>tag</span> <Your feedback matters>" should not contain the following lines of text:
      | &quot;&amp;&gt;&lt; |

    When I fill in "Subject" with "The Big Opening is postponed"
    And I fill in "Body" with:
    """
    In this email we are testing the available arguments:
    <a href="@group_landing_page">The <group> landing page URL</a>
    <a href="@group_members_page">The <group> members page URL</a>
    The recipient's full name: @recipient_name
    <a href="@recipient_profile">The recipient's profile URL</a>
    <a href="@recipient_profile_edit">The recipient's profile edit URL</a>
    <a href="@recipient_subscriptions">The recipient's subscriptions page URL</a>
    """

    When I press "Update"
    Then I should see the success message "The announcement has been updated but it's still in Draft state. Review the announcement and, when is ready to be sent, use the 'Send' button."
    And I should see "Announcement state: Draft"

    When I press "Preview"
    Then I should see the success message "A sample announcement has been delivered to sonsoftweets@example.com."
    And the email sent to "Sons of tweets" with subject "The Big Opening is postponed" contains the following lines of text:
      | Dear Sons of tweets,                                                                                                                                                                                                                                                            |
      | Fail pension <group> has an announcement for its members:                                                                                                                                                                                                                       |
      | The Big Opening is postponed                                                                                                                                                                                                                                                    |
      | In this email we are testing the available arguments:                                                                                                                                                                                                                           |
      | The <group> members page URL                                                                                                                                                                                                                                                    |
      | The recipient's full name: Sons of tweets                                                                                                                                                                                                                                       |
      | The recipient's profile URL                                                                                                                                                                                                                                                     |
      | The recipient's profile edit URL                                                                                                                                                                                                                                                |
      | The recipient's subscriptions page URL                                                                                                                                                                                                                                          |
      | Kind regards,                                                                                                                                                                                                                                                                   |
      | The Fail pension <group> team                                                                                                                                                                                                                                                   |
      | You received this e-mail because, as a registered user of Interoperable Europe Portal and a member of Fail pension <group>, you agreed to receive information sent to you by the Fail pension <group> owner/administrator (based on Article 5(d) of Regulation (EU) 2018/1725). |
      | In case you do not want to receive such e-mails, you can unsubscribe from group announcement notifications or manage all your subscription notifications from the user subscriptions page.                                                                                      |
      | This is an unmonitored email address, please do not reply. You can contact directly Fail pension <group> via the Overview page.                                                                                                                                                 |

    When I press "Send for approval"
    Then I should see the success message "Your announcement has been sent for validation by Interoperable Europe Portal moderators. Once approved, will reach the Fail pension <group> members."
    And I should see the link "Add announcement"
    # The facilitator has exhausted all operations they can perform, so they
    # cannot edit the announcement anymore.
    And I should not see "edit" in the "The Big Opening is postponed" row
    # ...but they can still see the message in read-only mode.
    When I click "The Big Opening is postponed"
    Then I should see the warning message "This announcement is in read-only mode because is pending for moderators approval."
    And the following fields should be disabled "Subject,Body"
    And I should not see the button "Create"
    And I should not see the button "Update"
    And I should not see the button "Approve"
    And I should not see the button "Send for approval"
    And I should not see the button "Reject"
    And I should not see the button "Undo reject"
    And I should not see the button "Preview"
    And the email sent to "check.edit.moderator@example.com" with subject "Interoperable Europe Portal: A new announcement 'The Big Opening is postponed' has been created" contains header "Content-Type" with value "text/html; charset=UTF-8; format=flowed"
    And the email sent to "check.edit.moderator@example.com" with subject "Interoperable Europe Portal: A new announcement 'The Big Opening is postponed' has been created" contains the following lines of text:
      | A new announcement 'The Big Opening is postponed' has been created by Sons of tweets for the Fail pension <group>. |
      | You can approve or reject the announcement at                                                                      |

    Given I am logged in as "Check edit moderator"

    When I go to the announcements page of "Hidden gems" <group>
    Then the response status code should be 403
    And I click the link from email sent to "Check edit moderator" with subject "Interoperable Europe Portal: A new announcement 'The Big Opening is postponed' has been created"
    Then I should see "Announcement state: Pending approval"
    And I should see the button "Update"
    And I should see the button "Approve"
    And I should see the button "Preview"
    But I should not see the button "Create"
    And I should not see the button "Send for approval"
    And I should not see the button "Undo reject"

    When I press "Reject"
    Then I should see the success message "The announcement has been rejected. Press the 'Undo reject' button to restore the announcement."
    Then I should see "Announcement state: Rejected"
    And I should see the button "Update"
    And I should see the button "Preview"
    But I should not see the button "Approve"
    And I should not see the button "Create"
    And I should not see the button "Send for approval"
    And I should not see the button "Reject"

    When I press "Preview"
    Then I should see the success message "A sample announcement has been delivered to check.edit.moderator@example.com."
    And the email sent to "Check edit moderator" with subject "The Big Opening is postponed" contains the following lines of text:
      | Dear Check edit moderator,                                                                                                                                                                                                                                                      |
      | Fail pension <group> has an announcement for its members:                                                                                                                                                                                                                       |
      | The Big Opening is postponed                                                                                                                                                                                                                                                    |
      | In this email we are testing the available arguments:                                                                                                                                                                                                                           |
      | The <group> landing page URL                                                                                                                                                                                                                                                    |
      | The <group> members page URL                                                                                                                                                                                                                                                    |
      | The recipient's full name: Check edit moderator                                                                                                                                                                                                                                 |
      | The recipient's profile URL                                                                                                                                                                                                                                                     |
      | The recipient's profile edit URL                                                                                                                                                                                                                                                |
      | The recipient's subscriptions page URL                                                                                                                                                                                                                                          |
      | Kind regards,                                                                                                                                                                                                                                                                   |
      | The Fail pension <group> team                                                                                                                                                                                                                                                   |
      | You received this e-mail because, as a registered user of Interoperable Europe Portal and a member of Fail pension <group>, you agreed to receive information sent to you by the Fail pension <group> owner/administrator (based on Article 5(d) of Regulation (EU) 2018/1725). |
      | In case you do not want to receive such e-mails, you can unsubscribe from group announcement notifications or manage all your subscription notifications from the user subscriptions page.                                                                                      |
      | This is an unmonitored email address, please do not reply. You can contact directly Fail pension <group> via the Overview page.                                                                                                                                                 |

    Given I am logged in as "Sons of tweets"

    When I go to the "The Big Opening is postponed" announcement of "Fail pension" <group>
    Then the response status code should be 403

    Given I am logged in as "Check edit moderator"

    When I go to the "The Big Opening is postponed" announcement of "Fail pension" <group>
    And  I press "Undo reject"
    Then I should see the success message "The announcement has been restored."
    Then I should see "Announcement state: Pending approval"
    And I should see the button "Update"
    And I should see the button "Approve"
    And I should see the button "Preview"
    But I should not see the button "Create"
    And I should not see the button "Send for approval"
    And I should not see the button "Undo reject"

    Given I am logged in as "Sons of tweets"

    When I go to the "The Big Opening is postponed" announcement of "Fail pension" <group>
    Then the response status code should be 403

    Given the "Mogul" user unsubscribes from "Fail pension" <group> announcements
    And the "Sons of tweets" user unsubscribes from "Fail pension" <group> announcements

    Given I am logged in as "Check edit moderator"

    When I go to the "The Big Opening is postponed" announcement of "Fail pension" <group>
    And I press "Approve"
    Then I should see the error message "Cannot send this announcement to Fail pension <group> because none of its members are subscribed to receive announcements."

    Given the "Mogul" user subscribes to "Fail pension" <group> announcements
    And the "Sons of tweets" user subscribes to "Fail pension" <group> announcements

    And I press "Approve"
    Then I should see the success message "The announcement has been delivered to the Fail pension <group> members."

    # Groups having up to 50 members are receiving the announcements instantly.
    And the email sent to "Mogul" with subject "The Big Opening is postponed" contains the following lines of text:
      | Dear Mogul,                                                                                                                                                                                                                                                                     |
      | Fail pension <group> has an announcement for its members:                                                                                                                                                                                                                       |
      | The Big Opening is postponed                                                                                                                                                                                                                                                    |
      | In this email we are testing the available arguments:                                                                                                                                                                                                                           |
      | The <group> landing page URL                                                                                                                                                                                                                                                    |
      | The <group> members page URL                                                                                                                                                                                                                                                    |
      | The recipient's full name: Mogul                                                                                                                                                                                                                                                |
      | The recipient's profile URL                                                                                                                                                                                                                                                     |
      | The recipient's profile edit URL                                                                                                                                                                                                                                                |
      | The recipient's subscriptions page URL                                                                                                                                                                                                                                          |
      | Kind regards,                                                                                                                                                                                                                                                                   |
      | The Fail pension <group> team                                                                                                                                                                                                                                                   |
      | You received this e-mail because, as a registered user of Interoperable Europe Portal and a member of Fail pension <group>, you agreed to receive information sent to you by the Fail pension <group> owner/administrator (based on Article 5(d) of Regulation (EU) 2018/1725). |
      | In case you do not want to receive such e-mails, you can unsubscribe from group announcement notifications or manage all your subscription notifications from the user subscriptions page.                                                                                      |
      | This is an unmonitored email address, please do not reply. You can contact directly Fail pension <group> via the Overview page.                                                                                                                                                 |
    And the email sent to "Sons of tweets" with subject "The Big Opening is postponed" contains the following lines of text:
      | Dear Sons of tweets,                                                                                                                                                                                                                                                            |
      | Fail pension <group> has an announcement for its members:                                                                                                                                                                                                                       |
      | The Big Opening is postponed                                                                                                                                                                                                                                                    |
      | In this email we are testing the available arguments:                                                                                                                                                                                                                           |
      | The <group> landing page URL                                                                                                                                                                                                                                                    |
      | The <group> members page URL                                                                                                                                                                                                                                                    |
      | The recipient's full name: Sons of tweets                                                                                                                                                                                                                                       |
      | The recipient's profile URL                                                                                                                                                                                                                                                     |
      | The recipient's profile edit URL                                                                                                                                                                                                                                                |
      | The recipient's subscriptions page URL                                                                                                                                                                                                                                          |
      | Kind regards,                                                                                                                                                                                                                                                                   |
      | The Fail pension <group> team                                                                                                                                                                                                                                                   |
      | You received this e-mail because, as a registered user of Interoperable Europe Portal and a member of Fail pension <group>, you agreed to receive information sent to you by the Fail pension <group> owner/administrator (based on Article 5(d) of Regulation (EU) 2018/1725). |
      | In case you do not want to receive such e-mails, you can unsubscribe from group announcement notifications or manage all your subscription notifications from the user subscriptions page.                                                                                      |
      | This is an unmonitored email address, please do not reply. You can contact directly Fail pension <group> via the Overview page.                                                                                                                                                 |

    And I should see the link "Add announcement"
    # The announcement cannot be edited anymore...
    But I should not see "edit" in the "The Big Opening is postponed" row
    # ...but it can be visualized in read-only mode.
    When I click "The Big Opening is postponed"
    Then I should see the warning message "This announcement is in read-only mode because it has been already delivered."
    And the following fields should be disabled "Subject,Body"
    And I should not see the button "Create"
    And I should not see the button "Update"
    And I should not see the button "Approve"
    And I should not see the button "Send for approval"
    And I should not see the button "Reject"
    And I should not see the button "Undo reject"
    And I should not see the button "Preview"

    # Now let's check the links inside email.
    When I click the "Fail pension" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the text "Fail pension"
    When I click the "The <group> landing page URL" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the text "Fail pension"
    When I click the "The <group> members page URL" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the heading "Members"
    When I click the "The recipient's profile URL" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the heading "Mogul"
    When I click the "The recipient's profile edit URL" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the heading "Mogul"
    And I should see the button "Save"
    When I click the "The recipient's subscriptions page URL" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the heading "Subscription settings"
    When I click the "unsubscribe" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the heading "Unsubscribe from Fail pension <group>?"
    When I click the "user subscriptions" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the heading "Subscription settings"
    When I click the "Overview" link in the email sent to "mogul@example.com" having the subject "The Big Opening is postponed"
    Then I should see the text "Fail pension"

    Given I am logged in as "Sons of tweets"

    When I go to the "The Big Opening is postponed" announcement of "Fail pension" <group>
    Then the response status code should be 403

    Given I am logged in as "Check edit moderator"

    # Switch off notifications for this group.
    When I go to the homepage of the "Fail pension" <group>
    And I click "Settings"
    And I uncheck the box "Announcements enabled"
    And I press "Save"

    Examples:
      | group      |
      | collection |
      | solution   |

  @javascript @group-e
  Scenario: Announcement moderation
    Given collection content:
      | title       |
      | Collection1 |
      | Collection2 |
    And solution content:
      | title                 | collection  |
      | Collection1 Solution1 | Collection1 |
      | Collection1 Solution2 | Collection1 |
      | Collection2 Solution1 | Collection2 |
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

    Then the "State" field should contain the "- Any -,Draft,Pending approval,Delivered,Rejected" options
    And the "announcement list" table should be:
      | Group                 | Subject            | State            | Created on | Author           | Edit |
      | Collection2 Solution1 | Announcement1 C2S1 | Delivered        | 03/08/2022 | Miau user        |      |
      | Collection2           | Announcement1 C2   | Pending approval | 29/07/2022 | Miau user        | edit |
      | Collection1 Solution1 | Announcement1 C1S1 | Rejected         | 19/12/2018 | Lory Facilitator | edit |
      | Collection1 Solution1 | Announcement2 C1S1 | Pending approval | 25/05/2018 | Lory Facilitator | edit |
      | Collection1           | Announcement1 C1   | Draft            | 05/11/2005 | Lory Facilitator | edit |
      | Collection1 Solution2 | Announcement1 C1S2 | Draft            | 30/04/1999 | Lory Facilitator | edit |

    When I type "Collection1 Solution1" in the "group" autocomplete field
    And I pick "Collection1 Solution1" from the autocomplete suggestions
    And I press "Apply"
    Then the "announcement list" table should be:
      | Group                 | Subject            | State            | Created on | Author           | Edit |
      | Collection1 Solution1 | Announcement1 C1S1 | Rejected         | 19/12/2018 | Lory Facilitator | edit |
      | Collection1 Solution1 | Announcement2 C1S1 | Pending approval | 25/05/2018 | Lory Facilitator | edit |

    When I select "Pending approval" from "State"
    And I press "Apply"
    Then the "announcement list" table should be:
      | Group                 | Subject            | State            | Created on | Author           | Edit |
      | Collection1 Solution1 | Announcement2 C1S1 | Pending approval | 25/05/2018 | Lory Facilitator | edit |

    When I click "edit" in the "Announcement2 C1S1" row
    And I press "Approve"
    Then I should see the success message "The announcement has been delivered to the Collection1 Solution1 solution members."

    When I click "Announcements" in the "Administration toolbar"
    Then the "announcement list" table should be:
      | Group                 | Subject            | State            | Created on | Author           | Edit |
      | Collection2 Solution1 | Announcement1 C2S1 | Delivered        | 03/08/2022 | Miau user        |      |
      | Collection2           | Announcement1 C2   | Pending approval | 29/07/2022 | Miau user        | edit |
      | Collection1 Solution1 | Announcement1 C1S1 | Rejected         | 19/12/2018 | Lory Facilitator | edit |
      # Once delivered, the announcement cannot be edited.
      | Collection1 Solution1 | Announcement2 C1S1 | Delivered        | 25/05/2018 | Lory Facilitator |      |
      | Collection1           | Announcement1 C1   | Draft            | 05/11/2005 | Lory Facilitator | edit |
      | Collection1 Solution2 | Announcement1 C1S2 | Draft            | 30/04/1999 | Lory Facilitator | edit |

    # A delivered announcement can still be visualized in read-only mode.
    When I click "Announcement2 C1S1"
    Then I should see the warning message "This announcement is in read-only mode because it has been already delivered."
    And the following fields should be disabled "Subject,Body"
    And I should not see the button "Create"
    And I should not see the button "Update"
    And I should not see the button "Approve"
    And I should not see the button "Send for approval"
    And I should not see the button "Reject"
    And I should not see the button "Undo reject"
    And I should not see the button "Preview"

  @group-clone
  Scenario: Sending mass-mailing announcements.
    Given I am logged in as a moderator
    When I go to the announcements page of "Open Source Observatory (OSOR)" collection
    And I click "Add announcement"
    And I fill in "Subject" with "TEST: Mass-mailing announcement"
    And I fill in "Body" with "This will gonna reach tens of thousands of Open Source Observatory (OSOR) group members."
    And I press "Create"
    Then I should see the success message "The announcement has been created as Draft. Review the announcement and, when is ready to be sent, use the 'Send' button."

    When I press "Send for approval"
    Then I should see the success message "Your announcement has been sent for validation by Interoperable Europe Portal moderators. Once approved, will reach the Open Source Observatory (OSOR) collection members."
    When I click "edit" in the "TEST: Mass-mailing announcement" row
    And I press "Approve"
    Then I should see the success message "The announcement has been scheduled to be delivered to the Open Source Observatory (OSOR) collection members. Because the collection has a large amount of members the emails will be sent in several batches."

    Given the delivery queue of "TEST: Mass-mailing announcement" announcement is processed
    Then the "TEST: Mass-mailing announcement" announcement should have been delivered for each group member

  @group-e
  Scenario: Wrong recipient's name in announcements.
    Given collection content:
      | title         | state     |
      | Wrong pension | published |
    And users:
      | Username   | E-mail                       | First name | Family name |
      | cgarnett67 | callista.garnett@example.com | Callista   | Garnett     |
      | cbarnett68 | callista.barnett@example.com | Callista   | Barnett     |
      | jgarnett68 | jonny.garnett@example.com    | Jonny      | Garnett     |
      | jbarnett67 | joana.barnett@example.com    | Joana      | Barnett     |
    And the following collection user membership:
      | collection    | user           |
      | Wrong pension | cgarnett67     |
      | Wrong pension | cbarnett68     |
      | Wrong pension | jgarnett68     |
      | Wrong pension | jbarnett67     |
    And the following legal document version:
      | Document     | Label | Published | Acceptance label                                                                                   | Content                                                    |
      | Legal notice | 1.2   | yes       | I have read and accept the <a href="[entity_legal_document:url]">[entity_legal_document:label]</a> | The information on this site is subject to a disclaimer... |
    And the following "Legal notice" acceptances:
      | cgarnett67     |
      | cbarnett68     |
      | jgarnett68     |
      | jbarnett67     |
    And the "cgarnett67" user subscribes to "Wrong pension" collection announcements
    And the "cbarnett68" user subscribes to "Wrong pension" collection announcements
    And the "jgarnett68" user subscribes to "Wrong pension" collection announcements
    And the "jbarnett67" user subscribes to "Wrong pension" collection announcements

    When I am logged in as a moderator
    Then I go to the members page of "Wrong pension"
    And I click "Add announcement"
    Then the following fields should be present "Subject,Body"

    And I fill in "Subject" with "The recipient names"
    And I fill in "Body" with "The recipient's full name: @recipient_name"
    And I press "Create"
    Then I should see the success message "The announcement has been created as Draft. Review the announcement and, when is ready to be sent, use the 'Send' button."

    And I press "Send for approval"
    Then I click "edit" in the "The recipient names" row
    And I press "Approve"
    Then I should see the success message "The announcement has been delivered to the Wrong pension collection members."

    And the email sent to "cgarnett67" with subject "The recipient names" contains the following lines of text:
      | The recipient's full name: Callista Garnett |
    And the email sent to "cbarnett68" with subject "The recipient names" contains the following lines of text:
      | The recipient's full name: Callista Barnett |
    And the email sent to "jgarnett68" with subject "The recipient names" contains the following lines of text:
      | The recipient's full name: Jonny Garnett |
    And the email sent to "jbarnett67" with subject "The recipient names" contains the following lines of text:
      | The recipient's full name: Joana Barnett |

    # Check mass-mails.
    Given users:
      | Username   | E-mail                       | First name | Family name |
      | sgarnett69 | samuel.garnett@example.com   | Samuel     | Garnett     |
      | sbrown70   | sally.brown@example.com      | Sally      | Brown       |
      | ljones71   | lisa.jones@example.com       | Lisa       | Jones       |
      | lwilson72  | luke.wilson@example.com      | Luke       | Wilson      |
      | asmith73   | alice.smith@example.com      | Alice      | Smith       |
      | adavis74   | aaron.davis@example.com      | Aaron      | Davis       |
      | bclark75   | ben.clark@example.com        | Ben        | Clark       |
      | bmartin76  | beth.martin@example.com      | Beth       | Martin      |
      | dwhite77   | daniel.white@example.com     | Daniel     | White       |
      | drichard78 | diana.richard@example.com    | Diana      | Richard     |
      | fmorris79  | fiona.morris@example.com     | Fiona      | Morris      |
      | fbaker80   | felix.baker@example.com      | Felix      | Baker       |
      | hrobin81   | hannah.robin@example.com     | Hannah     | Robin       |
      | hthomas82  | harry.thomas@example.com     | Harry      | Thomas      |
      | kscott83   | kyle.scott@example.com       | Kyle       | Scott       |
      | klee84     | kelly.lee@example.com        | Kelly      | Lee         |
      | myoung85   | mia.young@example.com        | Mia        | Young       |
      | mking86    | matt.king@example.com        | Matt       | King        |
      | oturner87  | olivia.turner@example.com    | Olivia     | Turner      |
      | owalker88  | owen.walker@example.com      | Owen       | Walker      |
      | pallen89   | paige.allen@example.com      | Paige      | Allen       |
      | phall90    | paul.hall@example.com        | Paul       | Hall        |
      | rcooper91  | ruby.cooper@example.com      | Ruby       | Cooper      |
      | rwood92    | ryan.wood@example.com        | Ryan       | Wood        |
      | schapman93 | sarah.chapman@example.com    | Sarah      | Chapman     |
      | sbell94    | sean.bell@example.com        | Sean       | Bell        |
      | tcampbell95| taylor.campbell@example.com  | Taylor     | Campbell    |
      | tbennett96 | tom.bennett@example.com      | Tom        | Bennett     |
      | vmorgan97  | victoria.morgan@example.com | Victoria   | Morgan      |
      | vcarter98  | vincent.carter@example.com   | Vincent    | Carter      |
      | wreed99    | will.reed@example.com        | Will       | Reed        |
      | wroberts00 | wendy.roberts@example.com    | Wendy      | Roberts     |
      | ygreen01   | yara.green@example.com       | Yara       | Green       |
      | ycollins02 | yuri.collins@example.com     | Yuri       | Collins     |
      | zharris03  | zara.harris@example.com      | Zara       | Harris      |
      | zward04    | zane.ward@example.com        | Zane       | Ward        |
      | cmurphy05  | camila.murphy@example.com    | Camila     | Murphy      |
      | cfisher06  | caleb.fisher@example.com     | Caleb      | Fisher      |
      | dstevens07 | daisy.stevens@example.com    | Daisy      | Stevens     |
      | dprice08   | dylan.price@example.com      | Dylan      | Price       |
      | eperry09   | eva.perry@example.com        | Eva        | Perry       |
      | edunn10    | ethan.dunn@example.com       | Ethan      | Dunn        |
      | fgray11    | freya.gray@example.com       | Freya      | Gray        |
      | fbryant12  | finn.bryant@example.com      | Finn       | Bryant      |
      | hross13    | hayden.ross@example.com      | Hayden     | Ross        |
      | hwright14  | hope.wright@example.com      | Hope       | Wright      |
      | epearry09  | evaa.perry@example.com       | Evaa       | Perry       |
      | eduann10   | ethana.dunn@example.com      | Ethana     | Dunn        |
      | fgraay11   | freyaa.gray@example.com      | Freyaa     | Gray        |
      | fbryaant12 | finna.bryant@example.com     | Finna      | Bryant      |
      | hrosas13   | haydena.ross@example.com     | Haydena    | Ross        |
      | hwriaght14 | hopea.wright@example.com     | Hopea      | Wright      |
    And the following collection user memberships:
      | collection    | user       |
      | Wrong pension | sgarnett69 |
      | Wrong pension | sbrown70   |
      | Wrong pension | ljones71   |
      | Wrong pension | lwilson72  |
      | Wrong pension | asmith73   |
      | Wrong pension | adavis74   |
      | Wrong pension | bclark75   |
      | Wrong pension | bmartin76  |
      | Wrong pension | dwhite77   |
      | Wrong pension | drichard78 |
      | Wrong pension | fmorris79  |
      | Wrong pension | fbaker80   |
      | Wrong pension | hrobin81   |
      | Wrong pension | hthomas82  |
      | Wrong pension | kscott83   |
      | Wrong pension | klee84     |
      | Wrong pension | myoung85   |
      | Wrong pension | mking86    |
      | Wrong pension | oturner87  |
      | Wrong pension | owalker88  |
      | Wrong pension | pallen89   |
      | Wrong pension | phall90    |
      | Wrong pension | rcooper91  |
      | Wrong pension | rwood92    |
      | Wrong pension | schapman93 |
      | Wrong pension | sbell94    |
      | Wrong pension | tcampbell95|
      | Wrong pension | tbennett96 |
      | Wrong pension | vmorgan97  |
      | Wrong pension | vcarter98  |
      | Wrong pension | wreed99    |
      | Wrong pension | wroberts00 |
      | Wrong pension | ygreen01   |
      | Wrong pension | ycollins02 |
      | Wrong pension | zharris03  |
      | Wrong pension | zward04    |
      | Wrong pension | cmurphy05  |
      | Wrong pension | cfisher06  |
      | Wrong pension | dstevens07 |
      | Wrong pension | dprice08   |
      | Wrong pension | eperry09   |
      | Wrong pension | edunn10    |
      | Wrong pension | fgray11    |
      | Wrong pension | fbryant12  |
      | Wrong pension | hross13    |
      | Wrong pension | hwright14  |
      | Wrong pension | epearry09  |
      | Wrong pension | eduann10   |
      | Wrong pension | fgraay11   |
      | Wrong pension | fbryaant12 |
      | Wrong pension | hrosas13   |
      | Wrong pension | hwriaght14 |
    And the following "Legal notice" acceptances:
      | sgarnett69 |
      | sbrown70   |
      | ljones71   |
      | lwilson72  |
      | asmith73   |
      | adavis74   |
      | bclark75   |
      | bmartin76  |
      | dwhite77   |
      | drichard78 |
      | fmorris79  |
      | fbaker80   |
      | hrobin81   |
      | hthomas82  |
      | kscott83   |
      | klee84     |
      | myoung85   |
      | mking86    |
      | oturner87  |
      | owalker88  |
      | pallen89   |
      | phall90    |
      | rcooper91  |
      | rwood92    |
      | schapman93 |
      | sbell94    |
      | tcampbell95|
      | tbennett96 |
      | vmorgan97  |
      | vcarter98  |
      | wreed99    |
      | wroberts00 |
      | ygreen01   |
      | ycollins02 |
      | zharris03  |
      | zward04    |
      | cmurphy05  |
      | cfisher06  |
      | dstevens07 |
      | dprice08   |
      | eperry09   |
      | edunn10    |
      | fgray11    |
      | fbryant12  |
      | hross13    |
      | hwright14  |
      | epearry09  |
      | eduann10   |
      | fgraay11   |
      | fbryaant12 |
      | hrosas13   |
      | hwriaght14 |
    And the "sgarnett69" user subscribes to "Wrong pension" collection announcements
    And the "sbrown70" user subscribes to "Wrong pension" collection announcements
    And the "ljones71" user subscribes to "Wrong pension" collection announcements
    And the "lwilson72" user subscribes to "Wrong pension" collection announcements
    And the "asmith73" user subscribes to "Wrong pension" collection announcements
    And the "adavis74" user subscribes to "Wrong pension" collection announcements
    And the "bclark75" user subscribes to "Wrong pension" collection announcements
    And the "bmartin76" user subscribes to "Wrong pension" collection announcements
    And the "dwhite77" user subscribes to "Wrong pension" collection announcements
    And the "drichard78" user subscribes to "Wrong pension" collection announcements
    And the "fmorris79" user subscribes to "Wrong pension" collection announcements
    And the "fbaker80" user subscribes to "Wrong pension" collection announcements
    And the "hrobin81" user subscribes to "Wrong pension" collection announcements
    And the "hthomas82" user subscribes to "Wrong pension" collection announcements
    And the "kscott83" user subscribes to "Wrong pension" collection announcements
    And the "klee84" user subscribes to "Wrong pension" collection announcements
    And the "myoung85" user subscribes to "Wrong pension" collection announcements
    And the "mking86" user subscribes to "Wrong pension" collection announcements
    And the "oturner87" user subscribes to "Wrong pension" collection announcements
    And the "owalker88" user subscribes to "Wrong pension" collection announcements
    And the "pallen89" user subscribes to "Wrong pension" collection announcements
    And the "phall90" user subscribes to "Wrong pension" collection announcements
    And the "rcooper91" user subscribes to "Wrong pension" collection announcements
    And the "rwood92" user subscribes to "Wrong pension" collection announcements
    And the "schapman93" user subscribes to "Wrong pension" collection announcements
    And the "sbell94" user subscribes to "Wrong pension" collection announcements
    And the "tcampbell95" user subscribes to "Wrong pension" collection announcements
    And the "tbennett96" user subscribes to "Wrong pension" collection announcements
    And the "vmorgan97" user subscribes to "Wrong pension" collection announcements
    And the "vcarter98" user subscribes to "Wrong pension" collection announcements
    And the "wreed99" user subscribes to "Wrong pension" collection announcements
    And the "wroberts00" user subscribes to "Wrong pension" collection announcements
    And the "ygreen01" user subscribes to "Wrong pension" collection announcements
    And the "ycollins02" user subscribes to "Wrong pension" collection announcements
    And the "zharris03" user subscribes to "Wrong pension" collection announcements
    And the "zward04" user subscribes to "Wrong pension" collection announcements
    And the "cmurphy05" user subscribes to "Wrong pension" collection announcements
    And the "cfisher06" user subscribes to "Wrong pension" collection announcements
    And the "dstevens07" user subscribes to "Wrong pension" collection announcements
    And the "dprice08" user subscribes to "Wrong pension" collection announcements
    And the "eperry09" user subscribes to "Wrong pension" collection announcements
    And the "edunn10" user subscribes to "Wrong pension" collection announcements
    And the "fgray11" user subscribes to "Wrong pension" collection announcements
    And the "fbryant12" user subscribes to "Wrong pension" collection announcements
    And the "hross13" user subscribes to "Wrong pension" collection announcements
    And the "hwright14" user subscribes to "Wrong pension" collection announcements
    And the "epearry09" user subscribes to "Wrong pension" collection announcements
    And the "eduann10" user subscribes to "Wrong pension" collection announcements
    And the "fgraay11" user subscribes to "Wrong pension" collection announcements
    And the "fbryaant12" user subscribes to "Wrong pension" collection announcements
    And the "hrosas13" user subscribes to "Wrong pension" collection announcements
    And the "hwriaght14" user subscribes to "Wrong pension" collection announcements

    Then I go to the members page of "Wrong pension"
    And I click "Add announcement"
    Then the following fields should be present "Subject,Body"

    And I fill in "Subject" with "The recipient names mass-email"
    And I fill in "Body" with "The recipient's full name: @recipient_name"
    And I press "Create"
    Then I should see the success message "The announcement has been created as Draft. Review the announcement and, when is ready to be sent, use the 'Send' button."

    And I press "Send for approval"
    Then I click "edit" in the "The recipient names mass-email" row
    And I press "Approve"
    Then I should see the success message "The announcement has been scheduled to be delivered to the Wrong pension collection members. Because the collection has a large amount of members the emails will be sent in several batches."

    Given the delivery queue of "The recipient names mass-email" announcement is processed
    Then the "The recipient names mass-email" announcement should have been delivered for each group member
