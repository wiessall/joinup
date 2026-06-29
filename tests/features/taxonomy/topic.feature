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

  @javascript
  Scenario: Topic-specific help text is displayed in the query presets field when editing a configurable listing paragraph.
    Given the following topics are enabled in the homepage:
      | E-justice |
    And I append "layout" to "topics_paragraph" field in "taxonomy_term" entity with following "E-justice" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |
    And I append "configurable_listing" to "topics_paragraph" field in "taxonomy_term" entity with following "E-justice" title:
      | paragraph_heading | E-justice heading |
    And I am logged in as a moderator
    When I go to the edit form of the "E-justice" topic
    And I "edit" from the "Topic term body" field the child 1 paragraph of 1 layout
    And I press "Help"
    Then I should see the text "Extra examples"
    And I should see the text "Current topic: E-justice"
