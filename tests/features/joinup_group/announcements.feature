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
    And the following <group> user membership:
      | <group>      | user           | roles       |
      | Fail pension | Sons of tweets | facilitator |
      | Hidden gems  | Sons of tweets | facilitator |

    Given I am an anonymous user
    When I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404
    When I go to the announcements page of "Hidden gems" <group>
    Then the response status code should be 404

    Given I am logged in as an "authenticated user"
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
    And I should not see the button "Undo"
    And I fill in "Subject" with "Test anon [rdf_entity:label]"
    And I fill in "Body" with "Lorem Ipsum is simply dummy text of the printing and typesetting industry."

    When I press "Create"
    Then I should see the success message "The announcement has been created as Draft. Review the announcement and, when is ready to be sent, use the 'Send' button."
    And the "Subject" field should contain "Test anon [rdf_entity:label]"
    And the "Body" field should contain "Lorem Ipsum is simply dummy text of the printing and typesetting industry."
    And I should see "Announcement in Draft state"
    And I should see the button "Send"
    But I should not see the button "Approve"
    And I should not see the button "Reject"
    And I should not see the button "Create"
    And I should not see the button "Undo"

    When I fill in "Subject" with "Test anon [rdf_entity:label] bis"
    And I fill in "Body" with "Beware, new release is out for [rdf_entity:label]"
    And I press "Update"
    Then I should see the success message "The announcement has been updated but it's still in Draft state. Review the announcement and, when is ready to be sent, use the 'Send' button."
    And I should see "Announcement in Draft state"

    When I press "Send"
    Then I should see the success message "Your announcement has been sent for validation by Joinup moderators. Once approved, will reach the Fail pension <group> members."
    And I should see the link "Add announcement"
    # The facilitator has exhausted all operations they can perform, so they
    # cannot edit the announcement anymore.
    And I should not see "edit" in the "Test anon [rdf_entity:label] bis" row
    And the email sent to "Check edit moderator" with subject "Joinup: A new announcement 'Test anon Fail pension bis' has been created" contains the following lines of text:
      | text                                                                                                             |
      | A new announcement 'Test anon Fail pension bis' has been created by Sons of tweets for the Fail pension <group>. |
      | You can approve or reject the announcement at                                                                    |

    Given I am logged in as "Check edit moderator"
    When I go to the announcements page of "Hidden gems" <group>
    Then the response status code should be 404
    And I click the link from email sent to "Check edit moderator" with subject "Joinup: A new announcement 'Test anon Fail pension bis' has been created"
    Then I should see "Announcement in Sent state"
    And I should see the button "Update"
    And I should see the button "Approve"
    But I should not see the button "Create"
    And I should not see the button "Send"
    And I should not see the button "Undo"

    When I press "Reject"
    Then I should see the success message "The announcement has been rejected. Press the 'Undo' button to restore the announcement."
    Then I should see "Announcement in Rejected state"
    And I should see the button "Update"
    But I should not see the button "Approve"
    But I should not see the button "Create"
    But I should not see the button "Send"
    But I should not see the button "Reject"

    Given I am logged in as "Sons of tweets"
    When I go the "Test anon [rdf_entity:label] bis" announcement of "Fail pension" <group>
    Then the response status code should be 403

    Given I am logged in as "Check edit moderator"
    When I go the "Test anon [rdf_entity:label] bis" announcement of "Fail pension" <group>
    And  I press "Undo"
    Then I should see the success message "The announcement has been restored."
    Then I should see "Announcement in Sent state"
    And I should see the button "Update"
    And I should see the button "Approve"
    But I should not see the button "Create"
    And I should not see the button "Send"
    And I should not see the button "Undo"

    Given I am logged in as "Sons of tweets"
    When I go the "Test anon [rdf_entity:label] bis" announcement of "Fail pension" <group>
    Then the response status code should be 403

    Given I am logged in as "Check edit moderator"
    When I go the "Test anon [rdf_entity:label] bis" announcement of "Fail pension" <group>
    And I press "Approve"
    Then I should see the success message "The announcement has been delivered to the Fail pension <group> members."
    And I should see the link "Add announcement"
    But I should not see "edit" in the "Test anon [rdf_entity:label] bis" row

    Given I am logged in as "Sons of tweets"
    When I go the "Test anon [rdf_entity:label] bis" announcement of "Fail pension" <group>
    Then the response status code should be 403

    # Switch of notifications for this group.
    Given I am logged in as "Check edit moderator"
    When I go to the homepage of the "Fail pension" <group>
    And I click "Settings"
    And I uncheck the box "Announcements enabled"
    And I press "Save"

    When I go the "Test anon [rdf_entity:label] bis" announcement of "Fail pension" <group>
    Then the response status code should be 403
    But I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404

    Given I am logged in as "Sons of tweets"
    When I go the "Test anon [rdf_entity:label] bis" announcement of "Fail pension" <group>
    Then the response status code should be 403
    But I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404

    Given I am logged in as an "authenticated user"
    When I go the "Test anon [rdf_entity:label] bis" announcement of "Fail pension" <group>
    Then the response status code should be 403
    But I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404

    Given I am an anonymous user
    When I go the "Test anon [rdf_entity:label] bis" announcement of "Fail pension" <group>
    Then I should see the heading "Sign in to continue"
    But I go to the announcements page of "Fail pension" <group>
    Then the response status code should be 404

    # Explicitly delete announcements created via UI.
    And I delete the "Test anon [rdf_entity:label] bis" announcement

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
      | collection  | user             | roles       |
      | Collection1 | Lory Facilitator | facilitator |
      | Collection2 | Miau user        | facilitator |

    Given I am logged in as "Xela moderator"
    When I click "Announcements" in the toolbar

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

    When I click "Announcements" in the toolbar
    Then the "announcement list" table should be:
      | Group                 | Subject            | State     | Created on | Author           | Edit |
      | Collection2 Solution1 | Announcement1 C2S1 | Delivered | 03/08/2022 | Miau user        |      |
      | Collection2           | Announcement1 C2   | Sent      | 29/07/2022 | Miau user        | edit |
      | Collection1 Solution1 | Announcement1 C1S1 | Rejected  | 19/12/2018 | Lory Facilitator | edit |
      # Once delivered, the announcement cannot be edited.
      | Collection1 Solution1 | Announcement2 C1S1 | Delivered | 25/05/2018 | Lory Facilitator |      |
      | Collection1           | Announcement1 C1   | Draft     | 05/11/2005 | Lory Facilitator | edit |
      | Collection1 Solution2 | Announcement1 C1S2 | Draft     | 30/04/1999 | Lory Facilitator | edit |
