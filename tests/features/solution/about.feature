@api @group-e
Feature: About this solution
  In order to expose basic information about my solutions
  As a solution owner or solution facilitator
  I need to be able to sum up information in an "About" page

  @terms
  Scenario: About page
    Given the following contacts:
      | name                    | email                        | Website URL                     |
      | Ariel Lucile            | ariel@nova.dk                | http://nova.dk, http://nova.com |
      | Maiken Bine, Peer Milla | maiken@nova.dk, peer@nova.dk | https://innovation.nova.org     |
    And owners:
      | name                 | type                         |
      | Nova Pharmaceuticals | Company, Industry consortium |
      | Senatier             | National authority           |
    And collection:
      | title | Monoclonal Antibody Development |
      | state | validated                       |
    And solutions:
      | title   | state     |
      | Gel     | validated |
      | Polymer | validated |
      | Protein | proposed  |
    And solution:
      | title               | Size exclusion chromatography                     |
      | description         | Separating molecules by size.                     |
      | logo                | logo.png                                          |
      | banner              | banner.jpg                                        |
      | contact information | Ariel Lucile, "Maiken Bine, Peer Milla"           |
      | owner               | Nova Pharmaceuticals, Senatier                    |
      | state               | validated                                         |
      | documentation       | text.pdf                                          |
      | language            | Italian, Kallawaya                                |
      | topic               | Demography, E-inclusion                           |
      | related solutions   | Gel, Polymer, Protein                             |
      | solution type       | Non-binding Instrument, Service Discovery Service |
      | spatial coverage    | Italy, Egypt                                      |
      | status              | Under development                                 |
      | collection          | Monoclonal Antibody Development                   |

    # The link to the about page should be visible on the solution homepage.
    When I am not logged in
    And I go to the homepage of the "Size exclusion chromatography" solution
    Then I should see the link "About"

    # All the public information from the basic fields should be visible on the
    # about page.
    When I click "About"

    # Clean URLs should be applied to the "About" subpage.
    Then I should be on "/collection/monoclonal-antibody-development/solution/size-exclusion-chromatography/about"

    # The description.
    Then I should see the text "Separating molecules by size."

    # The grey area for owner and contact information.
    And I should see the text "Owner/Contact Information"

    # Multiple owners.
    And I should see the text "Owner"
    And I should see the following lines of text:
      | Nova Pharmaceuticals |
      | Senatier             |

    # Multiple instances of Contact information, each potentially having
    # multiple names, e-mail addresses and websites.
    And I should see the text "Contact information"
    And I should see the following lines of text:
      | Ariel Lucile |
      | Maiken Bine  |
      | Peer Milla   |
    And I should not see the text "E-mail address"
    And I should see the text "Website URL"
    And I should see the following links:
      | http://nova.com             |
      | http://nova.dk              |
      | https://innovation.nova.org |
    And I should not see the following links:
      | ariel@nova.dk               |
      | maiken@nova.dk              |
      | peer@nova.dk                |

    # Categorisation grey area.
    And I should see the text "Categorisation"
    And I should see the following lines of text:
      | Non-binding instrument    |
      | Service Discovery Service |

    And I should see the text "Status"
    And I should see the text "Under development"

    And I should see the text "Languages"
    And I should see the following lines of text:
      | Kallawaya |
      | Italian   |

    # Moderation grey area.
    And I should see the text "Moderation"

    # The rest of the fields should not be seen at the moment.
    # Multiple spatial coverage entries.
    And I should not see the following lines of text:
      | Egypt |
      | Italy |

    # Multiple topics.
    And I should see the text "Demography" in the "Header" region
    And I should see the text "E-inclusion" in the "Header" region
    But I should not see the text "Demography" in the "Content" region
    And I should not see the text "E-inclusion" in the "Content" region

    # Multiple related solutions.
    And I should not see the following lines of text:
      | Gel     |
      | Polymer |

    # A related solution which is not yet approved should not be visible.
    And I should not see the text "Protein"

  @terms @javascript @email
  Scenario: About page contact form
    Given the following contacts:
      | name                    | email                        | Website URL    |
      | Elise Lucile            | elise@nova.dk                | http://nova.dk |
    And owners:
      | name                 | type                  |
      | Rotterdam            | National authority    |
    And user:
      | Username    | Pally Rapamichael           |
      | First name  | Pally                       |
      | Family name | Rapamichael                 |
      | E-mail      | p.papamichael@pally.de      |
    And collection:
      | title | Antibody Development |
      | state | validated            |
    And solutions:
      | title   | state     |
      | Gelo    | validated |
    And solution:
      | title               | Six exclusion advantages                          |
      | description         | Separating molecules by weight.                   |
      | logo                | logo.png                                          |
      | banner              | banner.jpg                                        |
      | contact information | Elise Lucile                                      |
      | owner               | Rotterdam                                         |
      | state               | validated                                         |
      | documentation       | text.pdf                                          |
      | language            | Italian, Kallawaya                                |
      | topic               | Demography, E-inclusion                           |
      | related solutions   | Gelo                                              |
      | solution type       | Non-binding Instrument, Service Discovery Service |
      | spatial coverage    | Italy, Egypt                                      |
      | status              | Under development                                 |
      | collection          | Antibody Development                              |

    When I am logged in as a moderator
    Then I go to the "Six exclusion advantages" solution
    And I open the header local tasks menu
    And I click "Edit" in the "Header" region
    And I scroll "Contact information" into view
    And I press "Add new" at the "Contact information" field
    And I wait for AJAX to finish
    And I fill in "Name" with "Invisible Cat"
    And I fill in "E-mail" with "invisible.cat@xpto.com"
    And I press "Create contact information"
    And I press "Publish"
    Then I should see the success message "A new confirmation message has been sent to invisible.cat@xpto.com."
    And the contact owner email sent to "invisible.cat@xpto.com" with subject "Joinup - Please validate your contact details" contains the following lines of text:
      | text                                                                                                                                                                   |
      | Dear Invisible Cat,                                                                                                                                                    |
      | Your email address invisible.cat@xpto.com is used as a contact detail for the following:                                                                               |
      | Six exclusion advantages                                                                                                                                               |
      | (or copy this URL to your browser) to benefit from a dedicated form which will allow your users to get in touch with you in a quick, secure and privacy-friendly way.  |
      | Unless this step is completed, you will not be able to receive any messages from interested users or members.                                                          |
      | If you think this action is not clear or not due, please contact Joinup Support at https://joinup.ec.europa.eu/contact.                                                |
      | Best regards,                                                                                                                                                          |
      | The Joinup Team                                                                                                                                                        |
    But I click the validate contact info email from the last email sent to "invisible.cat@xpto.com"
    Then I should not see the text "Confirm invisible.cat@xpto.com"
    And I press "Send"
    Then I should see the success message "Email confirmation confirmed."

    When I am not logged in
    And I go to the homepage of the "Six exclusion advantages" solution
    Then I should see the link "About"

    When I click "About"
    Then I should be on "/collection/antibody-development/solution/six-exclusion-advantages/about"
    And I should see the text "Owner/Contact Information"
    And I should see the link "Contact owner"
    And I should not see the link "elise@nova.dk"
    Then I click "Contact owner"
    And a modal should open
    And I should see the text "Email Contact" in the "Modal title"
    When I fill in "Your name" with "Jonny Example"
    When I fill in "Your e-mail address" with "example@test.test"
    When I fill in "Subject" with "New reclamation about Owner"
    When I fill in "Message" with "This is reclamation test."
    And I should see the button "Send e-mail"
    Then I wait for the honeypot time limit to pass
    And I press "Send e-mail"

    Then I should see the success message "Your message has been sent."
    And the contact owner email sent to "invisible.cat@xpto.com" with subject "New reclamation about Owner" contains the following lines of text:
      | text                               |
      | Name: Jonny Example                |
      | Email: example@test.test           |
      | Message: This is reclamation test. |

    Given I am logged in as "Pally Rapamichael"
    And I go to the homepage of the "Six exclusion advantages" solution
    Then I click "About"

    And I should see the text "Owner/Contact Information"
    And I should see the link "Contact owner"
    And I should not see the link "elise@nova.dk"

    Then I click "Contact owner"
    And a modal should open
    And I should see the text "Email Contact" in the "Modal title"
    When I fill in "Subject" with "New reclamation about solution"
    When I fill in "Message" with "This is solution reclamation test."
    And I should see the button "Send e-mail"
    Then I wait for the honeypot time limit to pass
    And I press "Send e-mail"

    Then I should see the success message "Your message has been sent."
    # Submitting the modal should not redirect anywhere - ISAICP-7290.
    And I should be on "/collection/antibody-development/solution/six-exclusion-advantages/about"
    And the contact owner email sent to "invisible.cat@xpto.com" with subject "New reclamation about solution" contains the following lines of text:
      | text                                        |
      | Name: Pally Rapamichael                     |
      | Email: p.papamichael@pally.de               |
      | Message: This is solution reclamation test. |

    When I am logged in as a moderator
    Then I go to the "Six exclusion advantages" solution
    And I open the header local tasks menu
    And I click "Edit" in the "Header" region
    And I scroll "Contact information" into view
    And I press "Edit" in the "Invisible Cat" row
    And I press "Update contact information"
    And I press "Publish"
    Then I should not see the success message "A new confirmation message has been sent to invisible.cat@xpto.com."

    When I open the header local tasks menu
    And I click "Edit" in the "Header" region
    And I scroll "Contact information" into view
    And I press "Edit" in the "Invisible Cat" row
    And I fill in "E-mail" with "invisible.cat.one@xpto.com"
    And I press "Update contact information"
    And I press "Publish"
    Then I should see the success message "A new confirmation message has been sent to invisible.cat.one@xpto.com."
    And the contact owner email sent to "invisible.cat.one@xpto.com" with subject "Joinup - Please validate your contact details" contains the following lines of text:
      | text                                                                                                                                                                   |
      | Dear Invisible Cat,                                                                                                                                                    |
      | Your email address invisible.cat.one@xpto.com is used as a contact detail for the following:                                                                           |
      | Six exclusion advantages                                                                                                                                               |
      | (or copy this URL to your browser) to benefit from a dedicated form which will allow your users to get in touch with you in a quick, secure and privacy-friendly way.  |
      | Unless this step is completed, you will not be able to receive any messages from interested users or members.                                                          |
      | If you think this action is not clear or not due, please contact Joinup Support at https://joinup.ec.europa.eu/contact.                                                |
      | Best regards,                                                                                                                                                          |
      | The Joinup Team                                                                                                                                                        |
    But I click the validate contact info email from the last email sent to "invisible.cat.one@xpto.com"
    Then I should not see the text "Confirm invisible.cat.one@xpto.com"
    And I press "Send"
    Then I should see the success message "Email confirmation confirmed."

    # Check when submit form has an error.
    Given I am an anonymous user
    When I go to the homepage of the "Six exclusion advantages" solution
    Then I click "About"
    And I should see the text "Owner/Contact Information"
    Then I click "Contact owner"
    And a modal should open
    And I should see the text "Email Contact" in the "Modal title"
    When I fill in "Your name" with "Jonny K"
    And I fill in "Your e-mail address" with "jonnyk"
    And I fill in "Subject" with "Test form error"
    And I fill in "Message" with "This is solution reclamation test."
    Then I wait for the honeypot time limit to pass
    And I press "Send e-mail"
    Then I should see the heading "Email Contact"
    And I should see the error message "You must enter a valid e-mail address."
    Then I fill in "Your e-mail address" with "jonnyk@test.com"
    And I wait for the honeypot time limit to pass
    Then I press "Send e-mail"
    And I should see the success message "Your message has been sent."
    And I should be on "/collection/antibody-development/solution/six-exclusion-advantages/about"
