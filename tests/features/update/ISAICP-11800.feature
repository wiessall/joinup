@api @group-clone
Feature: Test ISAICP-11701

  @update:joinup_core_deploy_201614
  Scenario Outline: Checks that new items are added
    Given <login step>
    When I go to "/collection/open-source-observatory-osor/open-source-contact-directory"
    Then I should see the link "Ukraine"
    And I should see the following lines of text:
      # Bulgaria
      | Data for Good                                                            |
      | Email: info@data-for-good.bg                                             |
      | Web: https://data-for-good.bg/en/                                        |
      # France
      | OW2                                                                      |
      | Email: https://www.ow2.org/view/About/Contacts                           |
      | Web: https://www.ow2.org/                                                |
      # Germany
      | DigitalService                                                           |
      | Web: https://digitalservice.bund.de/en                                   |
      | Open Knowledge Foundation                                                |
      | Email: info@okfn.org                                                     |
      | Web: https://okfn.org/en/                                                |
      # Poland
      | Reszka Free Software Foundation (Fundacja Wolnego Oprogramowania Reszka) |
      | Web: https://reszka.org/                                                 |
      | Instrat                                                                  |
      | Email: patryk.berus@instrat.pl                                           |
      | Web: https://instrat.pl/o-fundacji-instrat/                              |
      # Ukraine
      | Kitsoft                                                                  |
      | Email: https://kitsoft.ua/contacts                                       |
      | Web: https://kitsoft.ua/                                                 |

    Examples:
      | login step                    |
      | I am an anonymous user        |
      | I am logged in as a moderator |
