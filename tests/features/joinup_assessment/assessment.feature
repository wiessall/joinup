@api @group-e @demoAssessment
Feature: Assessments

  Scenario: Assessment reports are listed in a distinct page and in search.
    Given I am an anonymous user
    When I go to "/collection/assessments/assessment-reports"
    And I see h1 span element with title "Assessment reports"
    And I should see the link "New assessment"
    And I should not see the link "My assessments"
    And I should not see the link "Search assessments"
    And I should see the text "There are no published assessment reports yet. You can add one by filling this questionnaire"
    And the following "assessment_report" content:
      | title         | created              | organization         | collection                         |
      | Assessment 1  | 2020-01-01T00:00:00Z | default organization | Portal support                        |
      | Assessment 2  | 2020-01-02T00:00:00Z | default organization | Portal support                        |
      | Assessment 3  | 2020-01-03T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 4  | 2020-01-04T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 5  | 2020-01-05T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 6  | 2020-01-06T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 7  | 2020-01-07T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 8  | 2020-01-08T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 9  | 2020-01-09T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 10 | 2020-01-10T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 11 | 2020-01-11T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 12 | 2020-01-12T00:00:00Z | default organization | Interoperability assessments space |

    When I go to the "Assessment reports" custom_page
    And I see h1 span element with title "Assessment reports"
    And I should see the link "New assessment"
    And I should not see the link "My assessments"
    And I should not see the link "Search assessments"
    And I should not see the text "There are no published assessment reports yet. You can add one by filling this questionnaire"
    # Reports are shown in creation descending order in a 10 pager listing.
    And I should see the following tiles in the correct order:
      | Assessment 12 |
      | Assessment 11 |
      | Assessment 10 |
      | Assessment 9  |
      | Assessment 8  |
      | Assessment 7  |
      | Assessment 6  |
      | Assessment 5  |
      | Assessment 4  |
      | Assessment 3  |
    # Hidden away in the second page.
    And I should not see the "Assessment 1" tile

    Given the following "custom_page" content:
      | title              | state     | collection                         |
      | Custom assessments | published | Interoperability assessments space |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Custom assessments" title:
      | content_listing:value | a:7:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:-1;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:33:"organization\|default organization";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |
    When I go to the "Custom assessments" custom_page
    And I see h1 span element with title "Custom assessments"
    And I should see the following tiles in the correct order:
      | Assessment 12 |
      | Assessment 11 |
      | Assessment 10 |
      | Assessment 9  |
      | Assessment 8  |
      | Assessment 7  |
      | Assessment 6  |
      | Assessment 5  |
      | Assessment 4  |
      | Assessment 3  |

    When I am logged in as a moderator
    When I go to "/collection/assessments/assessment-reports"
    And I see h1 span element with title "Assessment reports"
    And I should see the link "New assessment"
    And I should see the link "My assessments"
    And I should not see the link "Search assessments"
    And I should not see the text "There are no published assessment reports yet. You can add one by filling this questionnaire"
    Then I set "short_title" as "Short title" for "Assessment 12" assessment_report submission
    Then I go to "/collection/assessments/assessment-reports"
    And I should not see the text "Assessment 12"
    And I should see the text "Short title"
    And I should see the text "default organization"
