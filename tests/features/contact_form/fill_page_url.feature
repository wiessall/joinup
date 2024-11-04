@api @group-c
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
    And I fill in "Page URL" with "/interoperable-europe/interoperable-europe"
    And I fill in "Subject" with "Friendly reminder"
    And I enter "I will be back..." in the "Message" wysiwyg editor
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
    And I fill "Page URL" with the absolute URL "/interoperable-europe/interoperable-europe"
    And I fill in "Subject" with "Mistery reminder"
    And I enter "I will be back soon..." in the "Message" wysiwyg editor
    Then I press "Submit"
    And I should see the following success messages:
      | success messages                                              |
      | Your message has been submitted. Thank you for your feedback. |

  @javascript
  Scenario: Regression test for ISAICP-8398
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8398

    Given users:
      | Username      | E-mail                    |
      | Julian Munson | julian.munson@example.com |
    And contact_information content:
      | name         | email                |
      | Rarty Kelley | rmartykelley@bar.com |
    And owner content:
      | name              |
      | Martinez Gonzalez |
    And collection content:
      | title | description          | owner             | contact information | state     |
      | Rumba | Destruction of Rumba | Martinez Gonzalez | Rarty Kelley        | published |
    And news content:
      | title         | headline | body | collection | field_state |
      | Tumba spatial | headline | body | Rumba      | published   |
    And the following collection user memberships:
      | collection | user          | roles              |
      | Rumba      | Julian Munson | owner, facilitator |
    Given I am not logged in
    Then I go to the contact form
    And I fill in "First name" with "Jonh"
    And I fill in "Last name" with "Chaves"
    And I fill in "Organisation" with "Lucotores State"
    And I fill in "E-mail address" with "jonh.chaves@free.pe"
    And I select "Inappropriate content" from "Category"
    And I fill in "Subject" with "Reminder to remove content"
    And I enter "I will be back soon..." in the "Message" wysiwyg editor
    Then I press "Submit"
    And I should see the following error messages:
      | error messages              |
      | Page URL field is required. |

    And I fill in "Page URL" with "/collection/rumba/news/tumba-spatial"
    And I wait for AJAX to finish
    Then I press "Submit"
    And I should see the following success messages:
      | success messages                                              |
      | Your message has been submitted. Thank you for your feedback. |
