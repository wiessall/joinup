@api @group-g
Feature: Fill Page URL field in Technical problem / bug report on contact form
  As anonymous user
  In order to avoid the Page URL value containing the domain
  I want the domain to be removed from the input if I enter it

  @javascript @antibot
  Scenario: The domain is automatically removed from Page URL field if user inputs it.
    Given I am not logged in

    When I go to the contact form
    And I fill in "First name" with "Túpac"
    And I fill in "Last name" with "Amaru"
    And I fill in "Organisation" with "Vilcabamba State"
    And I fill in "E-mail address" with "tupac.amaru@free.pe"
    And I select "Technical problem / bug report" from "Category"
    And I fill in "Page URL" with "/collection/interoperable-europe/interoperable-europe"
    And I fill in "Subject" with "Friendly reminder"
    And I enter "I will be back..." in the "Message" wysiwyg editor
    Then I press "Submit"
    And I should see the following success messages:
      | success messages                                              |
      | Your message has been submitted. Thank you for your feedback. |

    When I go to the contact form
    When I go to the contact form
    And I fill in "First name" with "Túpac"
    And I fill in "Last name" with "Amaru"
    And I fill in "Organisation" with "Vilcabamba State"
    And I fill in "E-mail address" with "tupac.amaru@free.pe"
    And I select "Technical problem / bug report" from "Category"
    And I fill in "Page URL" with "/collection/interoperable-europe/interoperable-europe"
    And I fill in "Subject" with "Friendly reminder"
    And I enter "I will be back..." in the "Message" wysiwyg editor
    Then I press "Submit"
    And I should see the following success messages:
      | success messages                                              |
      | Your message has been submitted. Thank you for your feedback. |
