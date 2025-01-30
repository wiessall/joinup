@api @group-a
Feature:
  As a member user
  I need to be able to accept the latest Code of Conduct document.

  Background:
    Given collection content:
      | title          | closed |
      | Knowledge Hive | yes    |
    Given solution content:
      | title      | collection     |
      | Think Tank | Knowledge Hive |
    Given custom_page content:
      | title              | collection     |
      | Agreement of usage | Knowledge Hive |
    And the "code of conduct" setting of the "Knowledge Hive" collection is set to "Agreement of usage" "custom page"
    And users:
      | Username         | E-mail                       | First name | Family name |
      | Cedric Hawthorne | cedric.hawthorne@example.com | Cedric     | Hawthorne   |
      | Eleanor Vaughn   | eleanor.vaughn@example.com   | Eleanor    | Vaughn      |
      | Pending member   | pending.member@example.com   | Pending    | member      |
      | Blocked member   | blocked.member@example.com   | Blocked    | member      |
    And the following collection user memberships:
      | collection     | user             | state   |
      | Knowledge Hive | Cedric Hawthorne | active  |
      | Knowledge Hive | Eleanor Vaughn   | active  |
      | Knowledge Hive | Pending member   | pending |
      | Knowledge Hive | Blocked member   | blocked |

  Scenario: User can accept the latest version of Code of Conduct
    Given I am logged in as "Cedric Hawthorne"

    When I visit the "Knowledge Hive" Code of Conduct consent page
    Then I should see the heading "Important information"
    And I should see the following lines of text:
      | To ensure safe and respectful environment for all members of Knowledge Hive, we are introducing a Code of conduct. |
      | If you wish to maintain your membership in Knowledge Hive, please confirm below:                                   |

    When I press "Accept"
    Then I should see the error message "You must accept the Code of conduct"

    When I check "I have read and agree to the Code of conduct of Knowledge Hive."
    And I press "Accept"
    Then I should see the success message "Thank you for confirming your agreement to the Code of Conduct."

    When I visit the "Knowledge Hive" Code of Conduct consent page
    Then I should get an access denied error

    # Update the CoC, check message presence.
    Given the Code of Conduct for "Knowledge Hive" collection is updated
    When I visit the "Knowledge Hive" collection
    Then I should see the warning message "We updated our Code of Conduct to ensure safe and respectful environment for all members of Knowledge Hive. You can accept or decline it here."
    And I visit the "Think Tank" solution
    And I should see the warning message "We updated our Code of Conduct to ensure safe and respectful environment for all members of Knowledge Hive. You can accept or decline it here."

    # Unpublish/publish the CoC, check message presence.
    Given the Code of Conduct for "Knowledge Hive" collection is unpublished
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    Given the Code of Conduct for "Knowledge Hive" collection is published
    When I visit the "Knowledge Hive" collection
    Then I should see the warning message "We updated our Code of Conduct"

    # Accept the new version of the CoC and ensure the message is gone.
    And I visit the "Knowledge Hive" Code of Conduct consent page
    When I check "accept"
    And I press "Accept"
    And I visit the "Knowledge Hive" collection
    And I should not see the warning message "We updated our Code of Conduct"
    And I visit the "Think Tank" solution
    And I should not see the warning message "We updated our Code of Conduct"

    # Ensure previously existing member sees the message.
    Given I am logged in as "Eleanor Vaughn"
    And I visit the "Knowledge Hive" collection
    And I should see the warning message "We updated our Code of Conduct"

    # Ensure anonymous and blocked/pending users do not see the message.
    Given I am not logged in
    And I visit the "Knowledge Hive" collection
    And I should not see the warning message "We updated our Code of Conduct"
    Given I am logged in as "Pending member"
    And I visit the "Knowledge Hive" collection
    And I should not see the warning message "We updated our Code of Conduct"
    Given I am logged in as "Blocked member"
    And I visit the "Knowledge Hive" collection
    And I should not see the warning message "We updated our Code of Conduct"
