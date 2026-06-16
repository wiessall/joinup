@api @casMockServer @group-d
Feature: Log in through EU Login
  In order to access all website of the European Commission with the same credentials
  As a user with an existing EU Login account
  I need to be able to register and log in to Interoperable Europe Portal using EU Login

  Scenario: A local account is auto-registered on user choice.
    Given CAS users:
      | Username    | E-mail                         | Password  | First name | Last name |
      | chucknorris | texasranger@chucknorris.com.eu | Qwerty098 | Chuck      | Norris    |

    Given I am on the homepage
    And I click "Sign in"

    # The user gets redirected to the CAS server.
    Then I should see the heading "Sign in to continue"
    When I fill in "E-mail address" with "texasranger@chucknorris.com.eu"
    And I fill in "Password" with "wrong password"
    And I press "Log in"
    Then I should see the inline form error message "Unrecognized user name or password."

    When I fill in "Password" with "Qwerty098"
    And I press "Log in"

    # The user gets redirected back to Drupal.
    Then I should see the success message "Fill in the fields below to let the Interoperable Europe Portal community learn more about you!"

    # The user has been redirected to its user account edit form.
    Then the following fields should be present "Email, First name, Family name, Photo, Country of origin, Professional domain, Business title"
    And the following fields should be present "Facebook, X, LinkedIn, GitHub, SlideShare, Youtube, Vimeo"
    But I should not see the text "Fail - Password length must be at least 8 characters."
    And I should not see the text "Password character length of at least 8"
    And I should not see the text "Fail - Password must contain at least 3 types of characters from the following character types: lowercase letters, uppercase letters, digits, special characters."
    And I should not see the text "Minimum password character types: 3"

    And the user chucknorris should have the following data in their user profile:
      | First name  | Chuck  |
      | Family name | Norris |

  Scenario: An existing user can log in through EU Login
    Given the following users:
      | Username    | E-mail           | First name | Family name |
      | jb007_local | 007-local@mi6.eu | JJaammeess | BBoonndd    |
    Given CAS users:
      | Username | E-mail     | Password           | First name | Last name | Local username |
      | jb007    | 007@mi6.eu | shaken_not_stirred | James      | Bond      | jb007_local    |

    Given I am on the homepage
    And I click "Sign in"
    Then I should see the heading "Sign in to continue"
    And I fill in "E-mail address" with "007@mi6.eu"
    When I fill in "Password" with "shaken_not_stirred"
    And I press "Log in"

    Then I should see the success message "You have been logged in."
    And I should not see the link "Sign in"

    # The profile entries are overwritten, except the username.
    And the user jb007_local should have the following data in their user profile:
      | Username    | jb007_local |
      | E-mail      | 007@mi6.eu  |
      | First name  | James       |
      | Family name | Bond        |

    # Check that the email gets synced from the EU Login server.
    Given I click "Sign out"
    And CAS users:
      | Username | E-mail             | Password           | First name | Last name | Local username |
      | jb007    | 007.changed@mi6.eu | shaken_not_stirred | James      | Bond      | jb007_local    |
    # We want to test the case when a user is changing their email upstream, on
    # EU Login, with one that collides with other user's email. These are very
    # rare and unlikely edge cases where we only throw an exception.
    And the following users:
      | Username   | E-mail             |
      | other_user | 007.changed@mi6.eu |

    Given I am on the homepage
    And I click "Sign in"
    Then I should see the heading "Sign in to continue"
    When I fill in "E-mail address" with "007.changed@mi6.eu"
    And I fill in "Password" with "shaken_not_stirred"
    When I press "Log in"
    Then I should see the error message "You've recently changed your EU Login account email but that email is already used in Interoperable Europe Portal by another user. You cannot login until, either you change your EU Login email or you contact support to fix the issue."
    And I should see the link "contact support"

    # Change the EU Login account email to a unique value.
    Given CAS users:
      | Username | E-mail           | Password           | First name | Last name | Local username |
      | jb007    | uniq@example.com | shaken_not_stirred | James      | Bond      | jb007_local    |

    When I am on the homepage
    And I click "Sign in"
    Then I should see the heading "Sign in to continue"
    When I fill in "E-mail address" with "uniq@example.com"
    And I fill in "Password" with "shaken_not_stirred"
    When I press "Log in"

    And the user jb007_local should have the following data in their user profile:
      | Username    | jb007_local      |
      | E-mail      | uniq@example.com |
      | First name  | James            |
      | Family name | Bond             |

  Scenario: Fields imported from EU Login cannot be edited locally.
    Given the following users:
      | Username            | E-mail        |
      | full_cas_profile    | f@example.com |
      | partial_cas_profile | p@example.com |
      | no_cas_profile      | n@example.com |
      | without_cas         | w@example.com |

    Given CAS users:
      | Username            | E-mail        | Password | First name | Last name | Local username      |
      | full_cas_profile    | f@example.com | 123      | Joe        | Doe       | full_cas_profile    |
      | partial_cas_profile | p@example.com | 123      |            | Roe       | partial_cas_profile |
      | no_cas_profile      | n@example.com | 123      |            |           | no_cas_profile      |

    # User with full profile data.
    Given I am on the homepage
    And I click "Sign in"
    When I fill in "E-mail address" with "f@example.com"
    When I fill in "Password" with "123"
    And I press "Log in"
    And I click "My account"

    When I click "Edit"
    Then the "First name" field should contain "Joe"
    And the "Family name" field should contain "Doe"
    And the following fields should be disabled "First name,Family name"
    But I should not see the text "Username"
    And I should not see the text "full_cas_profile"
    And I should see the following lines of text:
      | Account information                                                                                                                                                                                                       |
      | Your name and E-mail are inherited from EU Login. To update this information, you can visit your EU Login account page. Synchronisation will take a few minutes and it will be visible the next time you login on Interoperable Europe Portal. |
      | Your e-mail address is not made public. We will only send you necessary system notifications and you can opt in later if you wish to receive additional notifications about content you are subscribed to.                |
      | Your first name is publicly visible.                                                                                                                                                                                      |
      | Your last name is publicly visible.                                                                                                                                                                                       |

    When I press "Save"
    Then I should see the success message "The changes have been saved."

    # User with partial profile data.
    Given I am an anonymous user
    And I am on the homepage
    And I click "Sign in"
    When I fill in "E-mail address" with "p@example.com"
    When I fill in "Password" with "123"
    And I press "Log in"
    And I click "My account"

    When I click "Edit"
    Then the "First name" field should contain ""
    And the "Family name" field should contain "Roe"
    And the following fields should not be disabled "First name"
    And the following fields should be disabled "Family name"
    But I should not see the text "Username"
    And I should not see the text "partial_cas_profile"
    And I should see the text "Your name and E-mail are inherited from EU Login. To update this information, you can visit your EU Login account page. Synchronisation will take a few minutes and it will be visible the next time you login on Interoperable Europe Portal."
    But I should not see the text "Fail - Password length must be at least 8 characters."
    And I should not see the text "Password character length of at least 8"
    And I should not see the text "Fail - Password must contain at least 3 types of characters from the following character types: lowercase letters, uppercase letters, digits, special characters."
    And I should not see the text "Minimum password character types: 3"

    When I press "Save"
    Then I should see the inline form error message "First name field is required."
    But I should not see the inline form error message "Family name field is required."

    # User with no profile data.
    Given I am an anonymous user
    And I am on the homepage
    And I click "Sign in"
    When I fill in "E-mail address" with "n@example.com"
    When I fill in "Password" with "123"
    And I press "Log in"
    And I click "My account"

    When I click "Edit"
    Then the "First name" field should contain ""
    And the "Family name" field should contain ""
    And the following fields should not be disabled "First name,Family name"
    But I should not see the text "Username"
    # The username appears in the page header because this use has no first and
    # last name. But we check the absence of "Username" and this is enough.
    And I should see the text "Your name and E-mail are inherited from EU Login. To update this information, you can visit your EU Login account page. Synchronisation will take a few minutes and it will be visible the next time you login on Interoperable Europe Portal."
    But I should not see the text "Fail - Password length must be at least 8 characters."
    And I should not see the text "Password character length of at least 8"
    And I should not see the text "Fail - Password must contain at least 3 types of characters from the following character types: lowercase letters, uppercase letters, digits, special characters."
    And I should not see the text "Minimum password character types: 3"

    When I press "Save"
    Then I should see the inline form error message "First name field is required."
    And I should see the inline form error message "Family name field is required."

    # User not linked to a CAS account.
    Given I am logged in as "without_cas"
    And I click "My account"

    When I click "Edit"
    Then the "First name" field should contain ""
    And the "Family name" field should contain ""
    And the following fields should not be disabled "First name,Family name"
    And I should see the text "Username"
    And I should see the text "without_cas"
    And I should see the text "The email address is not made public. It will only be used if you need to be contacted about your account or for opted-in notifications."

    When I press "Save"
    Then I should see the inline form error message "First name field is required."
    And I should see the inline form error message "Family name field is required."

  Scenario: A new user tries to register with an existing Email.
    Given the following users:
      | Username | E-mail          |
      | joe      | joe@example.com |

    And CAS users:
      | Username | E-mail          | Password |
      | joe_doe  | joe@example.com | 123      |

    Given I am on the homepage
    And I click "Sign in"
    When I fill in "E-mail address" with "joe@example.com"
    When I fill in "Password" with "123"
    And I press "Log in"

    Then I should see the following error message:
      | error messages                                                                                                                                                                                                                                           |
      | While trying to register your account with Interoperable Europe Portal, we found that your EU Login email address joe@example.com is already in use on our site. Either change your EU Login email address or contact the Interoperable Europe Portal support if you feel that something is wrong. |

  Scenario: A new user tries to register with an existing username.
    Given the following users:
      | Username | E-mail          |
      | joe      | joe@example.com |

    And CAS users:
      | Username | E-mail              | Password |
      | joe      | joe.cas@example.com | 123      |

    Given I am on the homepage
    And I click "Sign in"
    When I fill in "E-mail address" with "joe.cas@example.com"
    When I fill in "Password" with "123"
    And I press "Log in"
    Then I should see the success message "Fill in the fields below to let the Interoperable Europe Portal community learn more about you!"

  Scenario: The Drupal registration tab has been removed and the /user/register
  route redirects to EU Login registration form.
    When I visit "/user/login"
    Then I should not see the link "Create new account"
    When I visit "/user/register"
    Then the url should match "/cas-mock-server/eim/external/register.cgi"

  Scenario: The CAS module 'Add CAS user(s)' functionality is dismantled.
    Given I am logged in as a moderator
    When I visit "/admin/people"
    Then I should not see the link "Add CAS user(s)"
    When I go to "/admin/people/create/cas-bulk"
    Then the response status code should be 404

  Scenario: A moderator is able to manually link a local user to its EU Login.
    Given user:
      | Username    | joe                              |
      | E-mail      | joe_case_insensitive@example.com |
      | First name  | Joe                              |
      | Family name | Doe                              |

    And CAS users:
      | Username | E-mail                           | Password | First name | Last name |
      | joe      | Joe_Case_Insensitive@example.com | 123      | Joe        | Doe       |

    Given I am logged in as a moderator
    And I click "People"
    When I click "Edit" in the "Joe Doe" row
    Then the "Allow user to log in via CAS" checkbox should not be checked

    Given I check "Allow user to log in via CAS"
    And I fill in "CAS Username" with "joe"
    When I press "Save"
    Then I should see the following success messages:
      | success messages                                                |
      | The user has been notified that their account has been updated. |
      | The changes have been saved.                                    |

    When I click "Edit" in the "Joe Doe" row
    Then the "Allow user to log in via CAS" checkbox should be checked
    And the "CAS Username" field should contain "joe"
    And the following fields should be disabled "Email"
    And the following field should not be present "Password"

    Given I am an anonymous user
    And I am on the homepage
    And I click "Sign in"
    And I fill in "E-mail address" with "Joe_Case_Insensitive@example.com"
    And I fill in "Password" with "123"
    When I press "Log in"
    Then I should see the success message "You have been logged in."
    # The email ends up getting the upstream email so that correct character casing is applied.
    And the user joe should have the following data in their user profile:
      | E-mail | Joe_Case_Insensitive@example.com |

  Scenario: Anonymous user is asked to log in when accessing a protected page
    Given the following users:
      | Username | E-mail         | First name | Family name | Roles     |
      | jonbon   | jon@example.eu | Jon        | Bon         | moderator |
    Given CAS users:
      | Username | E-mail              | Password  | First name | Last name | Local username |
      | jbon     | j.bon@ec.example.eu | abc123!#$ | John       | Bonn      | jonbon         |
    Given I am an anonymous user
    When I visit "admin/people"
    Then I should see the heading "Sign in to continue"
    # The warning that the user is not authenticated should not be shown since
    # if we are using an external CAS authentication service the first Drupal
    # page that would be presented to the user would receive this message and at
    # that moment the user has successfully logged in. In this case we are using
    # a mocked CAS server which is written in Drupal and receives this message.
    # It needs to be suppressed.
    But I should not see the inline form error message "Access denied. You must sign in to view this page."
    When I fill in the following:
      | E-mail address | j.bon@ec.example.eu |
      | Password       | abc123!#$           |
    And I press "Log in"
    # The user should be redirected to the original page after logging in.
    Then I should see the heading "People"

  Scenario: Password reset is disabled.
    Given I am an anonymous user
    When I go to "/user/password"
    Then the response status code should be 404
    Given I am logged in as an "authenticated user"
    When I go to "/user/password"
    Then the response status code should be 404

  Scenario: As a developer I can temporary disable the site registration.
    Given CAS users:
      | Username | E-mail          | Password |
      | joe_doe  | joe@example.com | 123      |
    And the following users:
      | Username | Roles     |
      | dev      | developer |

    Given I am logged in as dev
    And I visit "/admin/config/people/cas"
    And I uncheck "Automatically register users"
    And I press "Save configuration"
    Then I should see the message "The configuration options have been saved."

    Given I am an anonymous user
    And I am on the homepage
    When I click "Sign in"
    When I fill in "E-mail address" with "joe@example.com"
    When I fill in "Password" with "123"
    And I press "Log in"
    Then I should see the error message "Registering new accounts to Interoperable Europe Portal is temporarily disabled. Please come back later."
    # Check that the user is not logged in.
    And I should see the link "Sign in"

    Given I am logged in as dev
    And I visit "/admin/config/people/cas"
    And I check "Automatically register users"
    And I press "Save configuration"
    Then I should see the message "The configuration options have been saved."

    Given I am an anonymous user
    And I am on the homepage
    When I click "Sign in"
    When I fill in "E-mail address" with "joe@example.com"
    When I fill in "Password" with "123"
    And I press "Log in"
    Then I should see the success message "Fill in the fields below to let the Interoperable Europe Portal community learn more about you!"
