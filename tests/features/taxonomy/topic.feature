@api @topic @group-i
Feature: Topic term routing and URL structure.

  Scenario: Topic terms are redirected to search unless present in the homepage queue.
    Given the following "topic" terms:
      | name     |
      | Foo term |
      | Bar term |
    And the following "collection" content:
      | title          | topic    |
      | Bar collection | Foo term |
    And the following topics are enabled in the homepage:
      | Bar term |

    When I go to the "Foo term" topic term page
    Then I should see the heading "Advanced search"
    And I should see the following facet summary "Foo term"

    When I go to the homepage
    And I click "Bar term"
    Then I should not see the heading "Advanced search"
    And I should see the heading "Bar term"

  Scenario: Topic term pages are accessible at the correct URL path.
    Given the following topics are enabled in the homepage:
      | Economy and Welfare     |
      | Info                    |
      | Statistics and Analysis |

    When I go to the "Economy and Welfare" topic term page
    Then the url should match "/topic/economy-and-welfare"

    When I go to the "Info" topic term page
    Then the url should match "/topic/info"

    When I go to the "Statistics and Analysis" topic term page
    Then the url should match "/topic/info/statistics-and-analysis"

    # Regression test: 3-dots is hidden for anonymous as only contains 'View'.
    Given I am an anonymous user
    And I visit "/topic/info/statistics-and-analysis"
    Then I should not see the button "Show primary options"

  Scenario: Moderators can only edit allowed fields on the topic edit form.
    Given I am logged in as a moderator
    And I go to "/dashboard"
    And I click Topics
    And I click "Edit" in the "E-justice" row
    Then I should see the heading "Edit E-justice"
    And the following fields should be present "Abstract, Description, Image, Body"
    But the following fields should not be present "Name"
    And I should not see the following lines of text:
      | Relations            |
      | Revision information |
    When I press "Save"
    Then I should see the following inline form error messages:
      | error messages                  |
      | Abstract field is required.     |

  Scenario: Show list of topics and check a given parent.
    Given I am logged in as a moderator
    When I go to "/dashboard"
    Then I should see the link Topics

    When I click Topics
    Then I should see the heading "Topics"
    And I should see the text "eGov" in the "Supplier exchange" row
    And I should see the text "Economy and Welfare" in the "Finance in EU" row
    And I should see the text "E-health Dpt." in the "E-health" row
    And I should see the text "HR Dpt." in the "Employment and Support Allowance" row
    And I should see the text "HR Dpt." in the "HR" row
    # Sorting by parent not allowed.
    And I should not see the link "Parent topic"

    # The following is not reproducible in the UI but is a backend test for
    # future changes in the topic vocabulary.
    Given the following "topic" terms:
      | tid                          | name        | description  |
      | http://some.topic/BlueParent | Blue parent | Hello there. |
    When I reload the page
    Then I should see the link "Blue parent"
    When the name of the "Blue parent" term is changed to "Red parent"
    And I reload the page
    Then I should see the link "Red parent"