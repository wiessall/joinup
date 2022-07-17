@group-clone @api @email
Feature: Test joinup_core_deploy_0108203.

  Scenario: Ensure email confirmation are send correctly.
    Given I run cron
    And the contact owner email sent to "joinup.legal@gmail.com" with subject "Joinup - Please validate your contact details" contains the following lines of text:
      | text                                                                                                                                                                   |
      | Dear joinup legal support,                                                                                                                                                    |
      | Your email address joinup.legal@gmail.com is used as a contact detail for the following:                                                                               |
      | Solution: ISA Contributor Agreement                                                                                                                                    |
      | (or copy this URL to your browser) to benefit from a dedicated form which will allow your users to get in touch with you in a quick, secure and privacy-friendly way.  |
      | Unless this step is completed, you will not be able to receive any messages from interested users or members.                                                          |
      | If you think this action is not clear or not due, please contact Joinup Support at https://joinup.ec.europa.eu/contact.                                                |
      | Best regards,                                                                                                                                                          |
      | The Joinup Team                                                                                                                                                        |
