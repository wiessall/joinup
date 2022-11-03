@group-clone @api @email
Feature: Test joinup_core_deploy_108701.

  Scenario: Ensure email confirmation are send correctly.
    Given I am an anonymous user

    When I go to the about page of "Access to Base Registries"
    Then I should not see the text "E-mail address"
    And I should not see the link "Contact" in the "Content" region

    When I go to the about page of "ABR - BRegDCAT-AP"
    Then I should not see the text "E-mail address"
    And I should not see the link "Contact" in the "Content" region

    When I go to the about page of "ABR - BRegDCAT-AP Tools"
    Then I should not see the text "E-mail address"
    And I should not see the link "Contact" in the "Content" region

    When I run cron
    # Note that emails are added in alphabetical order in the queue. That's why,
    # for the purpose of this test, we chose one from the top to make sure that
    # the queue entry is consumed on first cron run.
    Then the email sent to "ABR@trasysinternational.com" with subject "Joinup - Please validate your contact details" contains the text:
      | Dear Joinup user,                                                                                                                                                           |
      | As owner/facilitator, we’re improving your privacy by replacing the publicly visible contact email information with a contact form that users/members can use to reach you. |
      | Please confirm your email address ABR@trasysinternational.com, which is set in your contact information for the following:                                                  |
      | Collection: Access to Base Registries                                                                                                                                       |
      | Solution: ABR - BRegDCAT-AP                                                                                                                                                 |
      | Solution: ABR - BRegDCAT-AP Tools                                                                                                                                           |
      | Confirm your e-mail address                                                                                                                                                 |
      | If the link doesn't work, just copy this URL to your browser:                                                                                                               |
      | If you do not validate your email within the next 5 days, you will not be able to receive any messages from users/members.                                                  |
      | If you think this action is not clear or not due, please contact Joinup Support at https://joinup.ec.europa.eu/contact                                                      |
      | Best regards,                                                                                                                                                               |
      | The Joinup Team                                                                                                                                                             |
    When I click the validate contact info email from the last email sent to "ABR@trasysinternational.com"
    And I press "Send"
    Then I should see the success message "Email confirmation confirmed."

    When I go to the about page of "Access to Base Registries"
    Then I should see the text "E-mail address"
    And I should see the link "Contact" in the "Content" region

    When I go to the about page of "ABR - BRegDCAT-AP"
    Then I should see the text "E-mail address"
    And I should see the link "Contact" in the "Content" region

    When I go to the about page of "ABR - BRegDCAT-AP Tools"
    Then I should see the text "E-mail address"
    And I should see the link "Contact" in the "Content" region
