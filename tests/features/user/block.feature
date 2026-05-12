@api @group-i
Feature: Block users
  As a moderator I must be able to block users.

  @javascript
  Scenario: A moderator can block a user.
    Given the following users:
      | Username     | Roles     | E-mail                   | First name | Family name |
      | Mod Murielle | Moderator | mod.murielle@example.com |            |             |
      | Liam Lego    |           | liam.lego@example.com    | Liam       | Lego        |
      | Liam Duplo   |           | liam.duplo@example.com   | Liam       | Duplo       |

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
      | recipient | Liam Lego                                                                                                                                      |
      | subject   | Your account was just blocked.                                                                                                                 |
      | body      | Your Interoperable Europe Portal account was recently blocked by our moderators. For more information about blocked accounts, please visit our |

    # Empty the inbox.
    Given I mark all emails as read

    # Unblock the user
    When I check "Liam Lego"
    Then I select "Unblock the selected user(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Unblock the selected user(s) was applied to 1 item."
    And 1 e-mail should have been sent
    And the following email should have been sent:
      | recipient          | Liam Lego                                                           |
      | subject            | Welcome to the Interoperable Europe Portal!                         |
      | body               | Your account has been activated and we are excited to have you join |
      | signature_required | no                                                                  |
    # Empty the inbox.
    Given I mark all emails as read

    # Block user for reason
    When I click "People"
    Then I check "Liam Lego"
    And I select "Block the selected user(s): Spam account" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Block the selected user(s): Spam account was applied to 1 item."
    When I check "Liam Duplo"
    And I select "Block the selected user(s): Suspicious account" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Block the selected user(s): Suspicious account was applied to 1 item."
    And 2 e-mail should have been sent
    And the "Users" table should contain the following columns:
      | Username   | Blocked reason |
      | Liam Lego  | Spam           |
      | Liam Duplo | Suspicious     |

    Given I mark all emails as read

    #Filter users
    When I click "People"
    And I select "Suspicious" from "Blocked reason"
    And I press "Filter"
    And the "Users" table should contain the following columns:
      | Username   | Blocked reason |
      | Liam Duplo | Suspicious     |
    And I select "Spam" from "Blocked reason"
    And I press "Filter"
    And the "Users" table should contain the following columns:
      | Username  | Blocked reason |
      | Liam Lego | Spam           |

    # Unblock by using the user edit form
    When I click "Edit" in the "Liam Lego" row
    And I select the radio button "Active"
    And I should not see "Blocked reason"
    And I press "Save"
    Then 1 e-mail should have been sent
    And the following email should have been sent:
      | recipient          | Liam Lego                                                           |
      | subject            | Welcome to the Interoperable Europe Portal!                         |
      | body               | Your account has been activated and we are excited to have you join |
      | signature_required | no                                                                  |

    # Empty the inbox.
    Given I mark all emails as read

    # Block by using the user edit form
    When I click "People"
    When I click "Edit" in the "Liam Lego" row
    And I select the radio button "Blocked"
    And I should see "Blocked reason"
    And no option from select "Blocked reason" is selected
    And I select "Spam" from "Blocked reason"
    And I press "Save"
    Then 1 e-mail should have been sent
    And the following email should have been sent:
      | recipient | Liam Lego                                                                                                                                      |
      | subject   | Your account was just blocked.                                                                                                                 |
      | body      | Your Interoperable Europe Portal account was recently blocked by our moderators. For more information about blocked accounts, please visit our |

    When I click "Liam Lego"
    Then I should see "Blocked reason"
    And I should see "Spam"

  Scenario: A non-moderator can not block a user.
    Given the following users:
      | Username     | Roles         | E-mail                   | First name | Family name |
      | Mod Murielle | administrator | mod.murielle@example.com |            |             |
      | Liam Lego    |               | liam.lego@example.com    | Liam       | Lego        |
      | Liam Duplo   |               | liam.duplo@example.com   | Liam       | Duplo       |

    And I am logged in as "Mod Murielle"
    When I click "People"
    Then I check "Liam Lego"
    And I select "Block the selected user(s): Spam account" from "Action"
    And I press "Apply to selected items"
    Then I should see the error message "No access to execute Block the selected user(s): Spam account"
    Then I check "Liam Lego"
    And I select "Block the selected user(s): Suspicious account" from "Action"
    And I press "Apply to selected items"
    Then I should see the error message "No access to execute Block the selected user(s): Suspicious account"
