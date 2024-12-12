@api @group-clone @demoAssessment
Feature: Assessments

  # Remove this after the new block has been added on the production environment.
  Background:
    Given I append "block" to "field_paragraphs_body" field in "node" entity with following "Assessment Reports" title:
      | paragraph_block:plugin_id | views_block:assessment_reports-list                                                                                                                                                          |
      | paragraph_block:settings  | a:6:{s:2:"id";s:35:"views_block:assessment_reports-list";s:5:"label";s:0:"";s:13:"label_display";b:0;s:8:"provider";s:5:"views";s:11:"views_label";s:0:"";s:14:"items_per_page";s:4:"none";} |

  Scenario: Create a document node out of a webform.
    Given users:
      | Username         | Roles     | E-mail                    | First name | Family name |
      | Regular          |           | regular@example.com       |            |             |
      | Notify moderator | moderator | notify_moderator@test.com | Notify     | Moderator   |

    Given I am logged in as Regular
    And I check "I agree to the Legal notice document"
    And I press "Submit"

    When I am at "/collection/assessments/assessment-reports/questionnaire"
    Then I see the text "Best practice for interoperability assessments" in the "Header" region
    And I should see the heading "Online form for Assessments"

    # 1st page.
    And I fill in "Please provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "Pioneer in making automobiles"
    And I select the radio button "No"
    And I press "Next"

    # 2nd page.
    When I check "Defense"
    And I fill in "Nr." with "-1"
    And I press "Next"
    Then I should see the error message "Nr. must be higher than or equal to 1."
    When I clear the field "Nr."
    And I press "Next"

    # 3rd page.
    When I select the radio button "No"
    And I press "Next"

    # 4th page.
    When I press "Submit"
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
    Then I should see the heading "Assessment Reports"
    And I should see the success message containing "The assessment submission report has been created: Assessment report #"
    And the following email should have been sent:
      | recipient | Regular                                                                               |
      | subject   | Your assessment has been published                                                    |
      | body      | Your submitted assessment questionnaire has been approved and the report is published |
      | body      | You can check all published assessments at                                            |

    # This submission has fulfilled its scope. It cannot be moderated anymore.
    When I go to the latest submission moderation form
    Then the response status code should be 403

    Given I am an anonymous user
    When I go to the latest assessment report
    Then I should see the following lines of text:
      | 1. General information                                                                                                                         |
      | Please select the type of public sector body {Empty}                                                                                           |
      | Please provide the name of the initiative, project or action containing the binding requirements to be assessed: Pioneer in making automobiles |
      | If applicable, please provide a link to the initiative, project or action concerned: {Empty}                                                   |
      | Can this assessment be linked to previous assessments or related assessments at EU or national level? No                                       |
      | 2. Binding requirements                                                                                                                        |
      | The binding requirements identified concern (please select) {Empty}                                                                            |
      | Please specify the concerned function of trans-European digital public service(s): Defense                                                     |
      | Please list the binding requirement(s) assessed. {Empty}                                                                                       |
      | This data will potentially be processed by: {Empty}                                                                                            |
      | Identified effects on cross-border legal interoperability {Empty}                                                                              |
      | Identified effects on cross-border organisational interoperability {Empty}                                                                     |
      | Identified effects on cross-border semantic interoperability {Empty}                                                                           |
      | Identified effects on cross-border technical interoperability {Empty}                                                                          |
      | 3. Results                                                                                                                                     |
      | Internal Solutions in the Portal {Empty}                                                                                                       |
      | Solutions not in the Portal (link) {Empty}                                                                                                     |
      | Have you discovered any remaining barriers to cross-border interoperability? No                                                                |
      | 4. Supporting documents                                                                                                                        |
      | Binding requirement documents {Empty}                                                                                                          |
      | Upload assessment documents {Empty}                                                                                                            |
      | Please add any other comment you would like to share. {Empty}                                                                                  |

    Given I mark all emails as read

    # After approval, the submission cannot be edite anymore.
    Given I am logged in as Regular
    When I go to the latest assessment submission edit form
    Then I should see the warning message "The assessment submission cannot be edited. Either is being moderated or it has been approved and the report published on the assessments report page."

    When I am at "/collection/assessments/assessment-reports/questionnaire"
    Then I should see the heading "Online form for Assessments"
    And I should see the following success messages:
      | success messages                                                                                              |
      | You have already submitted this webform. View your previous submission.                                       |
      | You're about to fill a new assessment questionnaire. The form is prefilled with your previous submitted data. |
    # New submission is prefilled with values from previous submission.
    And the "Please provide the name of the initiative, project or action containing the binding requirements to be assessed:" field should contain "Pioneer in making automobiles"

    When I click "View your previous submission"
    Then I should see the following lines of text:
      | 1. General information                                                                                                                         |
      | Please select the type of public sector body {Empty}                                                                                           |
      | Please provide the name of the initiative, project or action containing the binding requirements to be assessed: Pioneer in making automobiles |
      | If applicable, please provide a link to the initiative, project or action concerned: {Empty}                                                   |
      | Can this assessment be linked to previous assessments or related assessments at EU or national level? No                                       |
      | 2. Binding requirements                                                                                                                        |
      | The binding requirements identified concern (please select) {Empty}                                                                            |
      | Please specify the concerned function of trans-European digital public service(s): Defense                                                     |
      | Please list the binding requirement(s) assessed. {Empty}                                                                                       |
      | This data will potentially be processed by: {Empty}                                                                                            |
      | Identified effects on cross-border legal interoperability {Empty}                                                                              |
      | Identified effects on cross-border organisational interoperability {Empty}                                                                     |
      | Identified effects on cross-border semantic interoperability {Empty}                                                                           |
      | Identified effects on cross-border technical interoperability {Empty}                                                                          |
      | 3. Results                                                                                                                                     |
      | Internal Solutions in the Portal {Empty}                                                                                                       |
      | Solutions not in the Portal (link) {Empty}                                                                                                     |
      | Have you discovered any remaining barriers to cross-border interoperability? No                                                                |
      | 4. Supporting documents                                                                                                                        |
      | Binding requirement documents {Empty}                                                                                                          |
      | Upload assessment documents {Empty}                                                                                                            |
      | Please add any other comment you would like to share. {Empty}                                                                                  |

    When I move backward one page
    And I fill in "Please provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "Abbey Road"
    And I select the radio button "No"
    And I press "Next"

    # 2nd page.
    When I check "Education"
    And I press "Next"

    # 3rd page.
    When I select the radio button "No"
    And I press "Save Draft"
    Then I should see the success message "Submission saved. You may return to this form later and it will restore the current values."

    When I go to "/collection/assessments/assessment-reports/questionnaire"
    # Latest draft is prefilled.
    Then I should see the following success messages:
      | success messages                                                                   |
      | You've already started to fill this form. Please complete the remaining questions. |
      | You have already submitted this webform. View your previous submission.            |

    When I press "Next"
    # 4th page.
    And I press "Submit"
    Then I should see the success message "Your assessment is being moderated. If approved, a report will be published on the assessment reports page. Moderators might require changes to this submission but you will be notified by email in this case."

    Given I am logged in as "Notify moderator"
    And I click the link from email sent to "Notify moderator" with subject "Assessment submission (Complete)"
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
      | body      | Please fix the issues at                                                                                |
      | body      | and submit the form again when done.                                                                    |

    # User is able to edit a 'Require updates' submission.
    Given I am logged in as "Regular"
    And I click the link from email sent to "Regular" with subject "Your assessment needs updates"
    When I fill in "Please provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "Let It Be"
    And I press "Next"
    And I press "Next"
    And I press "Next"
    And I press "Submit"
    Then I should see the success message "Your assessment is being moderated. If approved, a report will be published on the assessment reports page. Moderators might require changes to this submission but you will be notified by email in this case."

  Scenario Outline: Check organization field value.
    Given I am logged in as an "authenticated user"
    And I check "I agree to the Legal notice document"
    And I press "Submit"

    When I am at "/collection/assessments/assessment-reports/questionnaire"
    Then I should see the heading "Online form for Assessments"

    # 1st page.
    And I fill in "Please provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "Pioneer in making automobiles"
    And I select "<public_sector_body>" from "Please select the type of public sector body"
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
    Then I see the text "Best practice for interoperability assessments" in the "Header" region
    And I should see the text "<organisation>"

    Examples:
      | public_sector_body         | institution                    | institution_name                | organisation                   |
      | European Union institution | Please select your Institution | European Parliament             | European Parliament            |
      | European Union body        | Please select your Body        | European Investment Bank        | European Investment Bank       |

  Scenario: Webforms can be downloaded in PDF format.
    Given users:
      | Username | Roles | E-mail              | First name | Family name |
      | Regular  |       | regular@example.com |            |             |

    When I am logged in as "Regular"
    And I go to "/collection/assessments/assessment-reports/questionnaire"
    Then I should not see the link "Download PDF" in the "Content" region

    When I check "I agree to the Legal notice document"
    And I press "Submit"
    # 1st page.
    And I fill in "Please provide the name of the initiative, project or action containing the binding requirements to be assessed:" with "Pioneer in making automobiles"
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
    And I check "I agree to the Legal notice document"
    And I press "Submit"
    And I go to the latest assessment submission
    Then I should see the heading "Access denied"
    When I go to the pdf download link of the latest assessment submission
    Then I should see the heading "Access denied"

  Scenario: Assessment reports are listed in a distinct page.
    # Publish the collection, otherwise the documents will end up in the
    # unpublished index, and will not show in the listing. This does not affect
    # the visibility of the documents in the view below as we force them as
    # published, but it does affect the custom page listing below.
    Given the workflow state of the "Best practice for interoperability assessments" collection is changed to "published"
    When I go to "/collection/assessments/assessment-reports"
    Then I should see the heading "Assessment Reports"
    And I should see the link "New assessment"
    And I should not see the link "My assessments"
    And I should see the text "There's are no published assessment reports yet. You can add one by filling this questionnaire"
    And document content:
      | title         | body               | state     | document type               | collection                                     | created              |
      | Assessment 1  | Assessment 1 body  | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-01T00:00:00Z |
      | Assessment 2  | Assessment 2 body  | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-02T00:00:00Z |
      | Assessment 3  | Assessment 3 body  | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-03T00:00:00Z |
      | Assessment 4  | Assessment 4 body  | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-04T00:00:00Z |
      | Assessment 5  | Assessment 5 body  | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-05T00:00:00Z |
      | Assessment 6  | Assessment 6 body  | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-06T00:00:00Z |
      | Assessment 7  | Assessment 7 body  | draft     | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-07T00:00:00Z |
      | Assessment 8  | Assessment 8 body  | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-08T00:00:00Z |
      | Assessment 9  | Assessment 9 body  | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-09T00:00:00Z |
      | Assessment 10 | Assessment 10 body | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-10T00:00:00Z |
      | Assessment 11 | Assessment 11 body | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-11T00:00:00Z |
      | Assessment 12 | Assessment 12 body | published | Interoperability Assessment | Best practice for interoperability assessments | 2020-01-12T00:00:00Z |

    When I go to "/collection/assessments/assessment-reports"
    Then I should see the heading "Assessment Reports"
    And I should see the link "New assessment"
    And I should not see the link "My assessments"
    And I should not see the text "There's are no published assessment reports yet. You can add one by filling this questionnaire"
    # Reports are shown in creation descending order in a 10 pager listing.
    And I should see the following tiles in the correct order:
      | Assessment 12 |
      | Assessment 11 |
      | Assessment 10 |
      | Assessment 9  |
      | Assessment 8  |
      | Assessment 6  |
      | Assessment 5  |
      | Assessment 4  |
      | Assessment 3  |
      | Assessment 2  |
    # Hidden away in the second page.
    And I should not see the "Assessment 1" tile

    # A custom page can be created for only showing documents of a given type.
    Given custom_page content:
      | title              | state     | collection                                     |
      | Custom assessments | published | Best practice for interoperability assessments |

    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Custom assessments" title:
      | content_listing:value | a:4:{s:7:"enabled";i:1;s:13:"query_presets";s:38:"field_type\|interoperability_assessment";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:2:"10";} |

    When I go to the "Custom assessments" custom_page
    Then I should see the heading "Custom assessments"
    And I should see the following tiles in the correct order:
      | Assessment 12 |
      | Assessment 11 |
      | Assessment 10 |
      | Assessment 9  |
      | Assessment 8  |
      | Assessment 6  |
      | Assessment 5  |
      | Assessment 4  |
      | Assessment 3  |
      | Assessment 2  |

    When I am logged in as a moderator
    When I go to "/collection/assessments/assessment-reports"
    Then I should see the heading "Assessment Reports"
    And I should see the link "New assessment"
    And I should see the link "My assessments"
    And I should not see the text "There's are no published assessment reports yet. You can add one by filling this questionnaire"
