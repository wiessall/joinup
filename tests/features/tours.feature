@api
Feature: Tours
  In order to understand complex concepts and user interfaces
  As a new user of the website
  I want to be able to follow tours that point out different sections of the page

  Scenario Outline: Anonymous user can access tours
    Given I am not logged in
    When I visit "<path>"
    Then a tour <expectation> available

    Examples:
      | path             | expectation   |
      | /                | should be     |
      | /keep-up-to-date | should be     |
      | /collections     | should not be |
      | /solutions       | should not be |
      # Recheck some URLs to ensure that cache contexts are working.
      | /                | should be     |
      | /solutions       | should not be |
      | /keep-up-to-date | should be     |

  Scenario Outline: Various user roles can access tours
    Given I am logged in as an "authenticated user"
    When I visit "<path>"

    Then a tour <expectation> available

    Examples:
      | path             | expectation   |
      # No button exists in the homepage.
      | /                | should not be |
      | /keep-up-to-date | should be     |
      | /collections     | should not be |
      | /solutions       | should not be |
      # Recheck some URLs to ensure that cache contexts are working.
      | /                | should not be |
      | /solutions       | should not be |
      | /keep-up-to-date | should be     |

  @javascript
  Scenario: A tour can be started from the 'Guided tour' button on the homepage
    Given I am on the homepage
    # Switch to a higher screen resolution. When running this test using the
    # default resolution on the test infrastructure an "Out of bounds error"
    # occurs and in the screenshots we can see that the "Guided tour" button is
    # just on the bottom edge of the display.
    And I use a device with an "extra extra large" display
    When I press "Guided tour"
    Then I should see the text "Sign in or out, and manage your user account"
    # Switch back to a smaller resolution.
    And I use a device with an "extra large" display
