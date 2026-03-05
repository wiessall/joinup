@api @group-i
Feature: User registration
  As a user I must be able to register to the site and complete my user profile
  and receive appropriate notifications.

  Scenario: A moderator can register a user
    Given users:
      | Username      | Roles     |
      | Mr. Moderator | Moderator |
    And I am logged in as "Mr. Moderator"
    When I am on the homepage
    Then I click "People"
    Then I click "Add user"
    Then I am at "admin/people/create"
    And I fill in "Email address" with "miomio@example.org"
    And I fill in "Username" with "miomio"
    And I fill in "Password" with "98eZRhoP"
    And I fill in "Confirm password" with "98eZRhoP"
    And I select the radio button "Active"
    And I check "Notify user of new account"
    And I fill in "First name" with "Miomir"
    And I fill in "Family name" with "Kurzmann"
    And I press "Create new account"
    Then the following email should have been sent:
      | recipient | miomio                                                                                                                          |
      | subject   | Your Interoperable Europe Portal account was created successfully.                                                                                   |
      | body      | The Portal Support Team created your account on Interoperable Europe Portal. Please log in through the following link in order to set your password. |
    # Only the email about the creation of the account should be sent. This
    # check ensures that we do not accidentally trigger any other notifications.
    And 1 e-mail should have been sent
    And the miomio user account is active

    # Clean up the account created through the UI.
    Then I delete the miomio user
