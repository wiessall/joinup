@api @group-f
Feature:
  As an owner of a website
  In order to guarantee data persistence
  All semantic assets need to have a persistent URI

  Scenario: Entities should have distinct URI pattern
    Given the following collection:
      | title | Persistent collection                                          |
      | logo  | logo.png                                                       |
      | state | published                                                      |
      | uri   | http://data.europa.eu/w21/37b8103e-26e5-4c81-8ce5-43ced02ff7d0 |
    And the following solution:
      | title       | Persistent solution                                            |
      | collection  | Persistent collection                                          |
      | description | Persistent solution                                            |
      | state       | published                                                      |
      | uri         | http://data.europa.eu/w21/ffb0ffc9-7704-45d3-95b3-42706b6320e5 |
    And the following release:
      | title          | Persistent release                                             |
      | release number | 23                                                             |
      | description    | Persistent release.                                            |
      | is version of  | Persistent solution                                            |
      | state          | published                                                      |
      | uri            | http://data.europa.eu/w21/98004ec9-62f3-4734-a1b6-af7e4838b09c |
    And the following distribution:
      | title       | Persistent distribution                                        |
      | description | Persistent distribution                                        |
      | access url  | test.zip                                                       |
      | parent      | Persistent solution                                            |
      | uri         | http://data.europa.eu/w21/643a2a52-da3b-4594-92bb-295d8134e1fb |
      | state       | published                                                      |
    And licence content:
      | title              | description        | uri                                                            |
      | Persistent licence | Persistent licence | http://data.europa.eu/w21/4205229d-92b6-4cac-80af-d8c2296d923c |
    # An arbitrary non-'publication office redirect'.
    And licence content:
      | title                    | uri                        |
      | Other Persistent licence | http://example.com/licence |
    # Has a URI that looks like a publication office redirect but has an
    # unsupported namespace.
    And licence content:
      | title                              | uri                                                               |
      | Wrong publication office namespace | http://data.europa.eu/unsupp/30aea866-fc89-4e45-b909-3d130acf49bf |

    And "eira" terms:
      | uri                                    | name         | description                                |
      | http://data.europa.eu/dr8/Dream/Domain | Dream Domain | You're safe from pain in the dream domain. |

    And discussion content:
      | title                 | body                  | collection            | state     |
      | Persistent discussion | Persistent discussion | Persistent collection | published |
    And document content:
      | title               | field_paragraphs_body | collection            | state     |
      | Persistent document | Persistent document   | Persistent collection | published |
    And event content:
      | title            | field_paragraphs_body | collection            | state     |
      | Persistent event | Persistent event      | Persistent collection | published |
    And news content:
      | title           | body            | collection            | state     |
      | Persistent news | Persistent news | Persistent collection | published |
    And custom_page content:
      | title           | body            | collection            | state     |
      | Persistent page | Persistent page | Persistent collection | published |

    When I go to the "Persistent release" release
    Then the persistent url should contain "http://data.europa.eu/w21"

    When I go to the "Persistent distribution" distribution
    Then the persistent url should contain "http://data.europa.eu/w21"

    When I go to the "Persistent licence" licence
    Then the persistent url should contain "http://data.europa.eu/w21"

    When I go to the "Other Persistent licence" licence
    Then the persistent url should contain "/node/"

    When I go to the "Wrong publication office namespace" licence
    Then the persistent url should contain "/node/"

    When I visit the "Persistent document" document
    Then the persistent url should contain "/node/"

    When I visit the "Persistent discussion" discussion
    Then the persistent url should contain "/node/"

    When I visit the "Persistent event" event
    Then the persistent url should contain "/node/"

    When I visit the "Persistent news" news
    Then the persistent url should contain "/node/"

    When I visit the "Persistent page" custom page
    Then the persistent url should contain "/node/"

    # Our semantic content has a persistent canonical path at Europe's
    # official data portal: "http://data.europa.eu/w21/{uuid}". The data portal
    # links back to the Joinup servers.
    Given I am on "data/w21/ffb0ffc9-7704-45d3-95b3-42706b6320e5"
    Then I should see the heading "Persistent solution"

    Given I am on "data/w21/37b8103e-26e5-4c81-8ce5-43ced02ff7d0"
    Then I should see the heading "Persistent collection"

    Given I am on "data/w21/98004ec9-62f3-4734-a1b6-af7e4838b09c"
    Then I should see the text "Persistent release 23" in the "Page title" region

    Given I am on "data/w21/643a2a52-da3b-4594-92bb-295d8134e1fb"
    Then I should see the heading "Persistent distribution"

    Given I am on "data/w21/4205229d-92b6-4cac-80af-d8c2296d923c"
    Then I should see the heading "Persistent licence"

    Given I am on "data/dr8/Dream/Domain"
    Then I should see the heading "Dream Domain"
    And I should see "You're safe from pain in the dream domain."
    And the url should match "/taxonomy/term/\d+"
