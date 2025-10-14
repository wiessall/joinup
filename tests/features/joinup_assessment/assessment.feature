@api @group-e @demoAssessment
Feature: Assessments

  Scenario: Create an assessment report out of a webform.
    Given users:
      | Username         | Roles     | E-mail                    | First name | Family name |
      | Regular          |           | regular@example.com       |            |             |
      | Notify moderator | moderator | notify_moderator@test.com | Notify     | Moderator   |

    Given I am logged in as Regular

    When I am at "/collection/assessments/assessment-reports/questionnaire"
    Then I see the text "Interoperability assessments space" in the "Header" region
    And I should see the heading "Online form for Assessments"

    # 1st page.
    And I fill in "Short title" with "Short title"
    And I fill in "Provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "Pioneer in making automobiles"
    And I select the radio button "No"
    And I press "Next"
    Then I should see the inline form error message "Type of public sector body field is required."

    When I select "Regional public sector body" from "Type of public sector body"
    And I press "Next"
    Then I should see the following inline form error messages:
      | error messages                               |
      | Select your country field is required.       |
      | Name of your organisation field is required. |

    When I select "Luxembourg" from "Select your country"
    Then I should see the inline form error message "Name of your organisation field is required."
    And I press "Next"

    When I fill in "Name of your organisation" with "ACME"

    When I select "European Union agency" from "Type of public sector body"
    And I press "Next"
    Then I should see the inline form error message "Select your Agency field is required."

    When I select "Eurojust" from "Select your Agency"
    And I press "Next"

    # 2nd page.
    When I check "Defense"
    And I fill in "Nr." with "-1"
    And I press "Next"
    Then I should see the inline form error message "Nr. must be higher than or equal to 1."
    When I clear the field "Nr."
    And I press "Next"

    # 3rd page.
    When I select the radio button "No"
    And I press "Next"

    # 4th page.
    When I attach the file "empty_pdf.pdf" to "Binding requirements supporting documents"
    And I attach the file "empty_pdf1.pdf" to "General Assessment supporting documents"
    And I press "Submit"
    Then I should see the success message "Your assessment is being moderated. If approved, a report will be published on the assessment reports page. Moderators might require changes to this submission but you will be notified by email in this case."

    # User cannot edit their submission while the submission is moderated.
    When I go to the latest assessment submission edit form
    Then I should see the warning message "The assessment submission cannot be edited. Either is being moderated or it has been approved and the report published on the assessments report page."
    And the following email should have been sent:
      | recipient_list_contains | Notify moderator                 |
      | subject                 | Assessment submission (Complete) |
      | body                    | Please moderate the submission   |
      | signature_required      | no                               |

    Given I am logged in as "Notify moderator"
    And I click the link from email sent to "Notify moderator" with subject "Assessment submission (Complete)"
    Then I should see the heading "Assessment Pioneer in making automobiles"

    When I select the radio button "Publish report"
    And I press "Submit"
    Then I should see the heading "Assessment reports"
    And I should see the success message containing "The assessment submission report has been created: Assessment report #"
    And the following email should have been sent:
      | recipient | Regular                                                                               |
      | subject   | Your assessment has been published                                                    |
      | body      | Your submitted assessment questionnaire has been approved and the report is published |
      | body      | You can check all published assessments at                                            |

    # This submission has fulfilled its scope. It cannot be moderated anymore.
    When I go to the latest submission moderation form
    Then the response status code should be 403

    # Moderators are able to see the submission is added to user profile.
    When I go to the profile of "Regular"
    And I click "Submissions"
    Then I should see "1 submission"
    And I should see "Submission title"
    And I should see "Created"
    And I should see "IP address"
    And I should see "Online form for Assessments: Submission #"
    # But regular users cannot see their submissions.
    Given I am logged in as "Regular"
    When I visit "/user"
    Then I should not see the link "Submissions"

    # @covers \joinup_assessment_webform_submission_access()
    Given I am an anonymous user
    When I go to the latest assessment report
    Then the "div.page-title__details" element should contain "Eurojust"
    And I should see text matching "Published on: \d{2}/\d{2}/\d{4}"
    And the "div.page-title__details" element should contain "Assessment report"
    Then I should see the following lines of text:
      | 1. General information                                                                                                                  |
      | Type of public sector body European Union agency                                                                                        |
      | Select your Agency Eurojust                                                                                                             |
      | Provide the name of the initiative, project or action containing the binding requirements to be assessed: Pioneer in making automobiles |
      | If applicable, provide a link to the initiative, project or action concerned: {Empty}                                                   |
      | Can this assessment be linked to previous assessments or related assessments at EU or national level? No                                |
      | 2. Binding requirements                                                                                                                 |
      | The binding requirements identified concern: {Empty}                                                                                    |
      | Specify the concerned function of trans-European digital public service(s): Defense                                                     |
      | List the binding requirement(s) assessed. {Empty}                                                                                       |
      | Select the groups of stakeholders to which binding requirement(s) are relevant: {Empty}                                                 |
      | Identified effects on cross-border legal interoperability {Empty}                                                                       |
      | Identified effects on cross-border organisational interoperability {Empty}                                                              |
      | Identified effects on cross-border semantic interoperability {Empty}                                                                    |
      | Identified effects on cross-border technical interoperability {Empty}                                                                   |
      | 3. Results                                                                                                                              |
      | Internal Solutions in the Portal {Empty}                                                                                                |
      | Solutions not in the Portal (link) {Empty}                                                                                              |
      | Have you discovered any remaining barriers to cross-border interoperability? No                                                         |
      | 4. Supporting documents                                                                                                                 |
      | Binding requirements supporting documents                                                                                               |
      | empty_pdf.pdf                                                                                                                           |
      | General Assessment supporting documents                                                                                                 |
      | empty_pdf1.pdf                                                                                                                          |
      | Please add any other comment you would like to share. {Empty}                                                                           |

    # Make sure assessment reports are not leaking into manage content list.
    When I visit "/collection/assessments/manage-content"
    Then I should not see "Assessment report"

    # Make sure assessment reports are not leaking in collection overview page.
    When I visit "/collection/assessments"
    Then I should not see "Assessment report #"

    # Make sure assessment reports are not leaking in the user profile page.
    When I visit "/user"
    Then I should not see "Assessment report #"

    Given I mark all emails as read
    And I am logged in as Regular
    # After approval, the submission cannot be edite anymore.
    When I go to the latest assessment submission edit form
    Then I should see the warning message "The assessment submission cannot be edited. Either is being moderated or it has been approved and the report published on the assessments report page."

    When I am at "/collection/assessments/assessment-reports/questionnaire"
    Then I should see the heading "Online form for Assessments"
    And I should see the following success messages:
      | success messages                                                                                              |
      | You have already submitted this webform. View your previous submission.                                       |
      | You're about to fill a new assessment questionnaire. The form is prefilled with your previous submitted data. |
    # New submission is prefilled with values from previous submission.
    And the "Provide the name of the initiative, project or action containing the binding requirements to be assessed:" field should contain "Pioneer in making automobiles"

    When I click "View your previous submission"
    Then I should see the following lines of text:
      | 1. General information                                                                                                                  |
      | Type of public sector body European Union agency                                                                                        |
      | Select your Agency Eurojust                                                                                                             |
      | Provide the name of the initiative, project or action containing the binding requirements to be assessed: Pioneer in making automobiles |
      | If applicable, provide a link to the initiative, project or action concerned: {Empty}                                                   |
      | Can this assessment be linked to previous assessments or related assessments at EU or national level? No                                |
      | 2. Binding requirements                                                                                                                 |
      | The binding requirements identified concern: {Empty}                                                                                    |
      | Specify the concerned function of trans-European digital public service(s): Defense                                                     |
      | List the binding requirement(s) assessed. {Empty}                                                                                       |
      | Select the groups of stakeholders to which binding requirement(s) are relevant: {Empty}                                                 |
      | Identified effects on cross-border legal interoperability {Empty}                                                                       |
      | Identified effects on cross-border organisational interoperability {Empty}                                                              |
      | Identified effects on cross-border semantic interoperability {Empty}                                                                    |
      | Identified effects on cross-border technical interoperability {Empty}                                                                   |
      | 3. Results                                                                                                                              |
      | Internal Solutions in the Portal {Empty}                                                                                                |
      | Solutions not in the Portal (link) {Empty}                                                                                              |
      | Have you discovered any remaining barriers to cross-border interoperability? No                                                         |
      | 4. Supporting documents                                                                                                                 |
      | Binding requirements supporting documents                                                                                               |
      | empty_pdf.pdf                                                                                                                           |
      | General Assessment supporting documents                                                                                                 |
      | empty_pdf1.pdf                                                                                                                          |
      | Please add any other comment you would like to share. {Empty}                                                                           |

    Then I mark all emails as read
    When I move backward one page
    And I fill in "Provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "Abbey Road"
    And I select the radio button "No"
    And I press "Next"

    # 2nd page.
    When I check "Education"
    And I press "Next"

    # 3rd page.
    When I select the radio button "No"
    And I press "Save Draft"
    Then I should see the success message "Submission saved. You may return to this form later and it will restore the current values."

    # Latest draft is prefilled.
    Then I go to the latest assessment submission edit form

    When I press "Next"
    # 4th page.
    And I press "Submit"
    Then I should see the success message "Your assessment is being moderated. If approved, a report will be published on the assessment reports page. Moderators might require changes to this submission but you will be notified by email in this case."
    And the following email should have been sent:
      | recipient_list_contains | Notify moderator                 |
      | subject                 | Assessment submission (Complete) |
      | body                    | Please moderate the submission   |
      | signature_required      | no                               |

    Given I am logged in as "Notify moderator"
    And I click the link from email sent to "Notify moderator" with subject "Assessment submission (Complete)"
    And the cache has been cleared
    And I reload the page
    And I should see "Assessment Abbey Road"
    When I select the radio button "Require updates"
    And I fill in "What updates are needed?" with "Please drastically reconsider this form. I mean it!"
    And I press "Submit"
    Then I should see the message "The assessment submission requires updates. The submitter has been notified."
    And the following email should have been sent:
      | recipient | Regular                                                                                                 |
      | subject   | Your assessment needs updates                                                                           |
      | body      | Your submitted assessment questionnaire has been moderated but the moderator has required some changes. |
      | body      | Rejection reason:                                                                                       |
      | body      | Please drastically reconsider this form. I mean it!                                                     |
      | body      | Please edit the form and fix the issues mentioned above. Submit the form again once everything is OK.   |

    # User is able to edit a 'Require updates' submission.
    Given I am logged in as "Regular"
    And I click the link from email sent to "Regular" with subject "Your assessment needs updates"
    When I fill in "Provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "Let It Be"
    And I press "Next"
    And I press "Next"
    And I press "Next"
    And I press "Submit"
    Then I should see the success message "Your assessment is being moderated. If approved, a report will be published on the assessment reports page. Moderators might require changes to this submission but you will be notified by email in this case."

    # Check the local/regional/national organizations case. Previous tests had
    # covered the case of EU organization type. Cover also the local, regional,
    # or national organizations cases.
    Given I mark all emails as read
    When I am at "/collection/assessments/assessment-reports/questionnaire"
    # 1st page.
    When I select "National public sector body" from "Type of public sector body"
    When I select "Luxembourg" from "Select your country"
    When I fill in "Name of your organisation" with "Pink Floyd"
    And I fill in "Provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "The Wall"
    And I select the radio button "No"
    And I press "Next"
    # 2nd page.
    When I check "Defense"
    And I press "Next"
    # 3rd page.
    When I select the radio button "No"
    And I press "Next"
    # 4th page.
    When I press "Submit"
    Then I should see the success message "Your assessment is being moderated. If approved, a report will be published on the assessment reports page. Moderators might require changes to this submission but you will be notified by email in this case."
    Given I am logged in as "Notify moderator"
    And I click the link from email sent to "Notify moderator" with subject "Assessment submission (Complete)"
    When I select the radio button "Publish report"
    And I press "Submit"

    When I go to the latest assessment report
    And I click "Bookmark" in the "Bookmark block" region
    Then I should see the link "Bookmarked" in the "Bookmark block"
    And I click "Like" in the "Like block" region
    And I should see the link "Unlike" in the "Like block"
    And I should see a Webtools eTrans element
    # Country is added in subtitle for national/regional/local organizations.
    # @covers \Drupal\joinup_assessment\Form\AssessmentModerationForm::createAssessmentReport()
    And the "div.page-title__details" element should contain "Pink Floyd (Luxembourg)"
    And I should see text matching "Published on: \d{2}/\d{2}/\d{4}"
    And the "div.page-title__details" element should contain "Assessment report"
    Then I should see the following lines of text:
      | 1. General information                                                                                             |
      | Type of public sector body National public sector body                                                             |
      | Select your country Luxembourg                                                                                     |
      | Name of your organisation Pink Floyd                                                                               |
      | Provide the name of the initiative, project or action containing the binding requirements to be assessed: The Wall |
      | If applicable, provide a link to the initiative, project or action concerned: {Empty}                              |
      | Can this assessment be linked to previous assessments or related assessments at EU or national level? No           |
      | 2. Binding requirements                                                                                            |
      | The binding requirements identified concern: {Empty}                                                               |
      | Specify the concerned function of trans-European digital public service(s): Defense                                |
      | List the binding requirement(s) assessed. {Empty}                                                                  |
      | Select the groups of stakeholders to which binding requirement(s) are relevant: {Empty}                            |
      | Identified effects on cross-border legal interoperability {Empty}                                                  |
      | Identified effects on cross-border organisational interoperability {Empty}                                         |
      | Identified effects on cross-border semantic interoperability {Empty}                                               |
      | Identified effects on cross-border technical interoperability {Empty}                                              |
      | 3. Results                                                                                                         |
      | Internal Solutions in the Portal {Empty}                                                                           |
      | Solutions not in the Portal (link) {Empty}                                                                         |
      | Have you discovered any remaining barriers to cross-border interoperability? No                                    |
      | 4. Supporting documents                                                                                            |
      | Binding requirements supporting documents                                                                                               |
      | empty_pdf.pdf                                                                                                                           |
      | General Assessment supporting documents                                                                                                 |
      | empty_pdf1.pdf                                                                                                                          |
      | Please add any other comment you would like to share. {Empty}                                                      |

    # Check that attachments are publicly available.
    Given I am an anonymous user
    When I go to the latest assessment report
    And I click "empty_pdf.pdf"
    Then the response status code should be 200
    Given I move backward one page
    And I click "empty_pdf1.pdf"
    Then the response status code should be 200

  Scenario Outline: Check organization field value.
    Given I am logged in as an "authenticated user"

    When I am at "/collection/assessments/assessment-reports/questionnaire"
    Then I should see the heading "Online form for Assessments"

    # 1st page.
    And I fill in "Short title" with "Short title"
    And I fill in "Provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "Pioneer in making automobiles"
    And I select "<public_sector_body>" from "Type of public sector body"
    And I select "<institution_name>" from "<institution>"
    And I select the radio button "No"
    And I press "Next"

    # 2nd page.
    When I check "Defense"
    And I press "Next"

    # 3rd page.
    When I select the radio button "No"
    And I press "Next"

    # 4th page.
    When I press "Submit"
    Then I should see the success message "Your assessment is being moderated. If approved, a report will be published on the assessment reports page. Moderators might require changes to this submission but you will be notified by email in this case."

    When I am at "/collection/assessments/assessment-reports/questionnaire/submissions"
    Then I see the text "Interoperability assessments space" in the "Header" region
    And I should see the text "<organisation>"

    Examples:
      | public_sector_body         | institution             | institution_name         | organisation             |
      | European Union institution | Select your Institution | European Parliament      | European Parliament      |
      | European Union body        | Select your Body        | European Investment Bank | European Investment Bank |

  Scenario: Webforms can be downloaded in PDF format.
    Given users:
      | Username | Roles | E-mail              | First name | Family name |
      | Regular  |       | regular@example.com |            |             |

    When I am logged in as "Regular"
    And I go to "/collection/assessments/assessment-reports/questionnaire"
    Then I should not see the link "Download PDF" in the "Content" region

    # 1st page.
    And I fill in "Short title" with "Short title"
    And I select "European Union agency" from "Type of public sector body"
    And I select "Eurojust" from "Select your Agency"
    And I fill in "Provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "Pioneer in making automobiles"
    And I select the radio button "No"
    And I press "Next"
    # 2nd page.
    When I check "Defense"
    And I press "Next"
    # 3rd page.
    When I select the radio button "No"
    And I press "Next"
    # 4th page.
    When I press "Submit"
    Then I should see the success message "Your assessment is being moderated. If approved, a report will be published on the assessment reports page. Moderators might require changes to this submission but you will be notified by email in this case."

    When I go to "/collection/assessments/assessment-reports/questionnaire"
    Then I should see the heading "Online form for Assessments"
    And I should see the text "You have already submitted this webform."
    And I should see the link "View your previous submission"

    When I press "Save Draft"
    Then I should see the success message "Submission saved. You may return to this form later and it will restore the current values."
    And I should see the link "Download PDF" in the "Content" region

    When I go to "/collection/assessments/assessment-reports/questionnaire/submissions"
    And I click "Download PDF"
    And the content type of the response should be "application/pdf"

    # Assert that the step works fine.
    When I go to the pdf download link of the latest assessment submission
    Then the content type of the response should be "application/pdf"

    When I am not logged in
    And I go to the latest assessment submission
    Then I should see the heading "Sign in to continue"
    When I go to the pdf download link of the latest assessment submission
    Then I should see the heading "Sign in to continue"

    When I am logged in as a user with the authenticated role
    And I go to the latest assessment submission
    Then I should see the heading "Access denied"
    When I go to the pdf download link of the latest assessment submission
    Then I should see the heading "Access denied"

  Scenario: Assessment reports are listed in a distinct page and in search.
    Given I am an anonymous user
    When I go to "/collection/assessments/assessment-reports"
    Then I should see the heading "Assessment reports"
    And I should see the link "New assessment"
    And I should not see the link "My assessments"
    And I should see the text "There are no published assessment reports yet. You can add one by filling this questionnaire"
    And assessment_report content:
      | title         | state     | created              | organization         | collection                         |
      | Assessment 1  | published | 2020-01-01T00:00:00Z | default organization | The Portal                         |
      | Assessment 2  | published | 2020-01-02T00:00:00Z | default organization | The Portal                         |
      | Assessment 3  | published | 2020-01-03T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 4  | published | 2020-01-04T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 5  | published | 2020-01-05T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 6  | published | 2020-01-06T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 7  | published | 2020-01-07T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 8  | published | 2020-01-08T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 9  | published | 2020-01-09T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 10 | published | 2020-01-10T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 11 | published | 2020-01-11T00:00:00Z | default organization | Interoperability assessments space |
      | Assessment 12 | published | 2020-01-12T00:00:00Z | default organization | Interoperability assessments space |

    When I go to the "Assessment reports" custom_page
    Then I should see the heading "Assessment reports"
    And I should see the link "New assessment"
    And I should not see the link "My assessments"
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

    Given custom_page content:
      | title              | state     | collection                         |
      | Custom assessments | published | Interoperability assessments space |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Custom assessments" title:
      | content_listing:value | a:7:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:-1;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:33:"organization\|default organization";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |
    When I go to the "Custom assessments" custom_page
    Then I should see the heading "Custom assessments"
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

    # Advanced search page.
    Given I am an anonymous user
    When I visit "/search"
    Then I should see the following "Content types" facet items "Collections,Assessment reports,Custom pages,User profiles" in this order
    When I click "Assessment reports"

    Then I should see the following tiles in the correct order:
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
    Then I should see the heading "Assessment reports"
    And I should see the link "New assessment"
    And I should see the link "My assessments"
    And I should not see the text "There are no published assessment reports yet. You can add one by filling this questionnaire"
