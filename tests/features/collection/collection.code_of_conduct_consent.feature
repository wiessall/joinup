@api @group-a
Feature:
  As a member user
  I need to be able to accept the latest Code of Conduct document.

  Background:
    Given collection content:
      | title          | closed |
      | Knowledge Hive | yes    |
    Given custom_page content:
      | title              | collection     |
      | Agreement of usage | Knowledge Hive |
    And the "code of conduct" setting of the "Knowledge Hive" collection is set to "Agreement of usage" "custom page"
    And users:
      | Username         | E-mail                       | First name | Family name |
      | Cedric Hawthorne | cedric.hawthorne@example.com | Cedric     | Hawthorne   |
    And the following collection user memberships:
      | collection     | user             | state  |
      | Knowledge Hive | Cedric Hawthorne | active |

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
