@api @group-clone @demoAssessment
Feature: Assessments

  Scenario: Create a document node out of a webform.
    Given users:
      | Username         | Roles     | E-mail                    | First name | Family name |
      | Regular          |           | regular@example.com       |            |             |
      | Notify moderator | moderator | notify_moderator@test.com | Notify     | Moderator   |

    Given I am logged in as Regular
    And I check "I agree to the Legal notice document"
    And I press "Submit"

    When I am at "/assessments/questionnaire"
    Then I should see the heading "Online form for Assessments"

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

    When I am at "/assessments/questionnaire"
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

    When I go to "/assessments/questionnaire"
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

    When I am at "/assessments/questionnaire"
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

    When I am at "/assessments/questionnaire/submissions"
    Then I should see the text "<organisation>"

    Examples:
      | public_sector_body         | institution                    | institution_name                | organisation                   |
      | European Union institution | Please select your Institution | European Parliament             | European Parliament            |
      | European Union body        | Please select your Body        | European Investment Bank        | European Investment Bank       |
