@group-clone @api @email
Feature: Test joinup_core_deploy_108500.

  Scenario: Ensure email confirmation are send correctly.
    Given I am an anonymous user
    When I go to the about page of "e-CODEX Connector Suite"
    Then I should not see the text "E-mail address"
    And I should not see the link "Contact owner"

    When I run cron
    Then the contact owner email sent to "mathias.maurer@brz.gv.at" with subject "Joinup - Please validate your contact details" contains the following lines of text:
      | text                                                                                                                                                                  |
      | Dear Joinup user,                                                                                                                                                     |
      | Your email address mathias.maurer@brz.gv.at is used as a contact                                                                                                      |
      | Solution: e-CODEX Connector Suite                                                                                                                                     |
      | (or copy this URL to your browser) to benefit from a dedicated form which will allow your users to get in touch with you in a quick, secure and privacy-friendly way. |
      | Unless this step is completed, you will not be able to receive any messages from interested users or members.                                                         |
      | If you think this action is not clear or not due, please contact Joinup Support at https://joinup.ec.europa.eu/contact.                                               |
      | Best regards,                                                                                                                                                         |
      | The Joinup Team                                                                                                                                                       |

    When I click the validate contact info email from the last email sent to "mathias.maurer@brz.gv.at"
    And I press "Send"
    Then I should see the success message "Email confirmation confirmed."

    When I go to the about page of "e-CODEX Connector Suite"
    Then I should see the text "E-mail address"
    And I should see the link "Contact owner"
