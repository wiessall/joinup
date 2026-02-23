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

  Scenario: PDF view display contains the same field labels as the default view
    Given solution content:
      | title         |
      | Test Solution |
    And public_organisation assessments:
      | name              | organisation_type          | country |
      | PDF Test Provider | european_union_institution | Belgium |
    And expression assessments:
      | name          |
      | PDF Test Expr |
    And public_service assessments:
      | name         |
      | PDF Test Svc |
    And participation assessments:
      | name            |
      | PDF Test Partic |
    And report assessments:
      | name            | state     | provider          | remaining_barriers                   | other_comment          | document | relevant_report             |
      | PDF View Report | published | PDF Test Provider | Some barriers remain to be addressed | This is a test comment | text.pdf | https://example.com/related |
    And the "PDF View Report" report has assessment statements of types:
      | type           |
      | legal          |
      | organisational |
      | semantic       |
      | technical      |
    And the following report has binding requirement:
      | report                 | PDF View Report            |
      | expression             | PDF Test Expr              |
      | service                | PDF Test Svc               |
      | participation          | PDF Test Partic            |
      | description            | PDF test BR                |
      | documented_in          | https://example.com/br-doc |
      | asset                  | Test Solution              |
      | requirement_documents  | text.pdf                   |
    # Report field group labels.
    Then the PDF HTML for the "PDF View Report" report should contain "General information"
    And the PDF HTML for the "PDF View Report" report should contain "Binding requirements"
    And the PDF HTML for the "PDF View Report" report should contain "Results"
    And the PDF HTML for the "PDF View Report" report should contain "Supporting documents and general comments"
    # Report field labels.
    And the PDF HTML for the "PDF View Report" report should contain "Identified effects on cross-border legal interoperability"
    And the PDF HTML for the "PDF View Report" report should contain "Identified effects on cross-border organisational interoperability"
    And the PDF HTML for the "PDF View Report" report should contain "Identified effects on cross-border semantic interoperability"
    And the PDF HTML for the "PDF View Report" report should contain "Identified effects on cross-border technical interoperability"
    And the PDF HTML for the "PDF View Report" report should contain "Remaining barriers of interoperability:"
    And the PDF HTML for the "PDF View Report" report should contain "The report has been connected to the following previous/relevant reports:"
    # Binding requirement paragraph labels.
    And the PDF HTML for the "PDF View Report" report should contain "Initiative"
    And the PDF HTML for the "PDF View Report" report should contain "Requirement"
    And the PDF HTML for the "PDF View Report" report should contain "Legal reference"
    And the PDF HTML for the "PDF View Report" report should contain "Concerned public services"
    And the PDF HTML for the "PDF View Report" report should contain "Affected stakeholders"
    And the PDF HTML for the "PDF View Report" report should contain "Interoperability solutions"
    And the PDF HTML for the "PDF View Report" report should contain "Supporting documents"
    # Sub-entity fields with hidden labels should not show labels in the PDF.
    And the PDF HTML for the "PDF View Report" report should not contain "Participant type"
    And the PDF HTML for the "PDF View Report" report should not contain "Stakeholder’s countries"
    And the PDF HTML for the "PDF View Report" report should not contain "Name of the initiative"
