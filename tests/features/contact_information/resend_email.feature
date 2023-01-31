@api @group-e
Feature: Resend validation email from collection/solution about page
    If the email address of a Collection/Solution has not been validated yet,
    then a message must be displayed under the about page

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

    Then I should not see the text "Please confirm your contact information to enable users to contact you"
