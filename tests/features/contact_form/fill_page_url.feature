@api @group-g
Feature: Fill Page URL field in Technical problem / bug report on contact form
  As anonymous user
  In order to avoid the Page URL value containing the domain
  I want the domain to be removed from the input if I enter it

  @javascript
  Scenario: The domain is automatically removed from Page URL field if user inputs it.
    Given I am not logged in
    Then I go to the contact form
    And I fill in "First name" with "Túpac"
    And I fill in "Last name" with "Amaru"
    And I fill in "Organisation" with "Vilcabamba State"
    And I fill in "E-mail address" with "tupac.amaru@free.pe"
    And I select "Technical problem / bug report" from "Category"
    And I fill in "Page URL" with "/collection/interoperable-europe/interoperable-europe"
    And I fill in "Subject" with "Friendly reminder"
    And I enter "I will be back..." in the "Message" wysiwyg editor
    # We need to wait 5 seconds for the spam protection time limit to pass.
    And I wait for the spam protection time limit to pass
    Then I press "Submit"
    And I should see the following success messages:
      | success messages                                              |
      | Your message has been submitted. Thank you for your feedback. |

    When I go to the contact form
    And I fill in "First name" with "Josie"
    And I fill in "Last name" with "Camaru"
    And I fill in "Organisation" with "Kiolin State"
    And I fill in "E-mail address" with "josie.camaru@free.pe"
    And I select "Technical problem / bug report" from "Category"
    And I fill "Page URL" with the absolute url "/collection/interoperable-europe/interoperable-europe"
    And I fill in "Subject" with "Mistery reminder"
    And I enter "I will be back soon..." in the "Message" wysiwyg editor
    # We need to wait 5 seconds for the spam protection time limit to pass.
    And I wait for the spam protection time limit to pass
    Then I press "Submit"
    And I should see the following success messages:
      | success messages                                              |
      | Your message has been submitted. Thank you for your feedback. |
