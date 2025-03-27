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
    Given the following user registration at "admin/people/create":
      | Email                      | miomio@example.org |
      | Username                   | miomio             |
      | First name                 | Miomir             |
      | Family name                | Kurzmann           |
      | Password                   | 98eZRhoP           |
      | Confirm password           | 98eZRhoP           |
      | Notify user of new account | 1                  |
      | Active                     | 1                  |
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

  Scenario: A user can register
    Given CAS users:
      | Username           | E-mail         | Password |
      | Iodine Maccalariat | iodine@ankh.am | 10d1ne   |
    When I am on the homepage
    And I click "Sign in"
    Then I should see the heading "Sign in to continue"
    When I fill in "E-mail address" with "iodine@ankh.am"
    And I fill in "Password" with "10d1ne"
    And I press "Log in"
    And the following email should have been sent:
      | recipient_mail | iodine@ankh.am                                                                                                                                                                                                                                                                  |
      | subject        | Your Interoperable Europe Portal account is pending approval. |
