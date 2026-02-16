@api @group-d
Feature: Assessment report PDF download
  Test PDF download functionality for assessment reports

  Scenario: Users can download PDF version of their assessments
    Given users:
      | Username       |
      | TestReportUser |
    And report assessments:
      | name                    | state     | uid            |
      | Infrastructure Review   | published | TestReportUser |
      | Security Assessment     | review    | TestReportUser |

    When I am logged in as "TestReportUser"
    And I visit "user/assessments"
    Then I should see the heading "My assessment reports"
    # PDF download should NOT appear in tiles, only on the full assessment page
    And I should not see the link "Download PDF"

    # Verify PDF link appears on individual assessment page.
    When I click "Infrastructure Review"
    Then I should see the link "Download PDF"

    # Verify PDF link on another assessment page.
    When I go to the "Security Assessment" report
    Then I should see the link "Download PDF"

    # Anonymous users should not see PDF download links.
    When I am an anonymous user
    And I go to the "Infrastructure Review" report
    Then I should not see the link "Download PDF"
