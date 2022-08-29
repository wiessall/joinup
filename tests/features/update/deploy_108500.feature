@group-clone @api @email @clearStaticCache
Feature: Test joinup_core_deploy_108500.

  Scenario: Ensure email confirmation are send correctly.
    Given I am an anonymous user

    When I go to the about page of "Access to Base Registries"
    Then I should not see the text "E-mail address"
    And I should not see the link "Contact owner"

    When I go to the about page of "ABR - BRegDCAT-AP"
    Then I should not see the text "E-mail address"
    And I should not see the link "Contact owner"

    When I go to the about page of "ABR - BRegDCAT-AP Tools"
    Then I should not see the text "E-mail address"
    And I should not see the link "Contact owner"

    When I run cron
    # Note that emails are added in alphabetical order in the queue. That's why,
    # for the purpose of this test, we chose one from the top to make sure that
    # the queue entry is consumed on first cron run.
    Then the contact owner email sent to "ABR@trasysinternational.com" with subject "Joinup - Please validate your contact details" contains the following lines of text:
      | text                                                                                                                                                                                                                                     |
      | Dear Joinup user,                                                                                                                                                                                                                        |
      | Your email address ABR@trasysinternational.com is used as a contact detail for the following:                                                                                                                                            |
      | Collection: Access to Base Registries                                                                                                                                                                                                    |
      | Solution: ABR - BRegDCAT-AP                                                                                                                                                                                                              |
      | Solution: ABR - BRegDCAT-AP Tools                                                                                                                                                                                                        |
      | To benefit from a dedicated form which will allow your users to get in touch with you in a quick, secure and privacy-friendly way, all you have to do is confirm your e-mail address by clicking on "Confirm your e-mail address" below. |
      | If the link doesn't work, just copy this URL to your browser:                                                                                                                                                                            |
      | Unless this step is completed, you will not be able to receive any messages from interested users or members.                                                                                                                            |
      | If you think this action is not clear or not due, please contact Joinup Support at https://joinup.ec.europa.eu/contact.                                                                                                                  |
      | Best regards,                                                                                                                                                                                                                            |
      | The Joinup Team                                                                                                                                                                                                                          |
    When I click the validate contact info email from the last email sent to "ABR@trasysinternational.com"
    And I press "Send"
    Then I should see the success message "Email confirmation confirmed."

    When I go to the about page of "Access to Base Registries"
    Then I should see the text "E-mail address"
    And I should see the link "Contact owner"

    When I go to the about page of "ABR - BRegDCAT-AP"
    Then I should see the text "E-mail address"
    And I should see the link "Contact owner"

    When I go to the about page of "ABR - BRegDCAT-AP Tools"
    Then I should see the text "E-mail address"
    And I should see the link "Contact owner"
