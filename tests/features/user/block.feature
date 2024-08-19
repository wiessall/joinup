@api @group-i
Feature: Block users
  As a moderator I must be able to block users.

  Scenario: A moderator can block a user.
    Given users:
      | Username     | Roles     | E-mail                   | First name | Family name |
      | Mod Murielle | Moderator | mod.murielle@example.com |            |             |
      | Liam Lego    |           | liam.lego@example.com    | Liam       | Lego        |

    # Search user
    And I am logged in as "Mod Murielle"
    Given I am on the homepage
    When I click "People"
    And I fill in "Name or email contains" with "Liam Lego"
    And I press "Filter"

    # Block the user
    Then I check "Liam Lego"
    And I select "Block the selected user(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Block the selected user(s) was applied to 1 item."
    And 1 e-mail should have been sent
    And the following email should have been sent:
      | recipient | Liam Lego                                                                                                                 |
      | subject   | Your account was just blocked.                                                                                            |
      | body      | Your Joinup account was recently blocked by our moderators. For more information about blocked accounts, please visit our |

    # Empty the inbox.
    Given I mark all emails as read

    # Unblock the user
    When I check "Liam Lego"
    Then I select "Unblock the selected user(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Unblock the selected user(s) was applied to 1 item."
    And 1 e-mail should have been sent
    And the following email should have been sent:
      | recipient | Liam Lego                                                           |
      | subject   | Welcome to the Interoperable Europe Portal!                         |
      | body      | Your account has been activated and we are excited to have you join |
    # Empty the inbox.
    Given I mark all emails as read

    # Block by using the user edit form
    When I click "Edit" in the "Liam Lego" row
    And I select the radio button "Blocked"
    And I press "Save"
    Then 1 e-mail should have been sent
    And the following email should have been sent:
      | recipient | Liam Lego                                                                                                                 |
      | subject   | Your account was just blocked.                                                                                            |
      | body      | Your Joinup account was recently blocked by our moderators. For more information about blocked accounts, please visit our |

    # Empty the inbox.
    Given I mark all emails as read

    # Unblock by using the user edit form
    When I click "Edit" in the "Liam Lego" row
    And I select the radio button "Active"
    And I press "Save"
    Then 1 e-mail should have been sent
    And the following email should have been sent:
      | recipient | Liam Lego                                                           |
      | subject   | Welcome to the Interoperable Europe Portal!                         |
      | body      | Your account has been activated and we are excited to have you join |
