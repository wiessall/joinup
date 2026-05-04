@api
Feature:
  As an owner of a website
  In order to guarantee data persistence
  All semantic assets need to have a persistent URI

  @group-d
  Scenario: Entities should have distinct URI pattern
    Given the following "collection" content:
      | title                 | logo     | uri                                                            |
      | Persistent collection | logo.png | http://data.europa.eu/w21/37b8103e-26e5-4c81-8ce5-43ced02ff7d0 |
    And the following "solution" content:
      | title               | collection            | description                     | uri                                                            |
      | Persistent solution | Persistent collection | Persistent solution description | http://data.europa.eu/w21/ffb0ffc9-7704-45d3-95b3-42706b6320e5 |
    And the following "release" content:
      | title              | release number | release notes      | is version of       | uri                                                            |
      | Persistent release | 23             | Persistent release | Persistent solution | http://data.europa.eu/w21/98004ec9-62f3-4734-a1b6-af7e4838b09c |
    And the following "distribution" content:
      | title                   | description             | resources | solution            | uri                                                            |
      | Persistent distribution | Persistent distribution | test.zip  | Persistent solution | http://data.europa.eu/w21/643a2a52-da3b-4594-92bb-295d8134e1fb |
    And the following "licence" content:
      | title              | description        | uri                                                            |
      | Persistent licence | Persistent licence | http://data.europa.eu/w21/4205229d-92b6-4cac-80af-d8c2296d923c |
    # An arbitrary non-'publication office redirect'.
    And the following "licence" content:
      | title                    | uri                        |
      | Other Persistent licence | http://example.com/licence |
    # Has a URI that looks like a publication office redirect but has an
    # unsupported namespace.
    And the following "licence" content:
      | title                              | uri                                                               |
      | Wrong publication office namespace | http://data.europa.eu/unsupp/30aea866-fc89-4e45-b909-3d130acf49bf |

    And "eira" terms:
      | uri                                    | name         | description                                |
      | http://data.europa.eu/dr8/Dream/Domain | Dream Domain | You're safe from pain in the dream domain. |

    And the following "discussion" content:
      | title                 | body                  | collection            |
      | Persistent discussion | Persistent discussion | Persistent collection |
    And the following "document" content:
      | title               | field_paragraphs_body | collection            |
      | Persistent document | Persistent document   | Persistent collection |
    And the following "event" content:
      | title            | field_paragraphs_body | collection            |
      | Persistent event | Persistent event      | Persistent collection |
    And the following "news" content:
      | title           | body            | collection            |
      | Persistent news | Persistent news | Persistent collection |
    And the following "custom_page" content:
      | title           | body            | collection            |
      | Persistent page | Persistent page | Persistent collection |

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

    When I visit the "Persistent page" "custom page"
    Then the persistent url should contain "/node/"

    # Our semantic content has a persistent canonical path at Europe's
    # official data portal: "http://data.europa.eu/w21/{uuid}". The data portal
    # links back to the Interoperable Europe Portal servers.
    Given I am on "data/w21/ffb0ffc9-7704-45d3-95b3-42706b6320e5"
    Then I should see "Persistent solution"
    And I should see "Persistent solution description"

    Given I am on "data/w21/37b8103e-26e5-4c81-8ce5-43ced02ff7d0"
    Then I should see "Persistent collection"

    Given I am on "data/w21/98004ec9-62f3-4734-a1b6-af7e4838b09c"
    Then I should see the text "Persistent release 23" in the "Page title" region

    Given I am on "data/w21/643a2a52-da3b-4594-92bb-295d8134e1fb"
    Then I should see the heading "Persistent distribution"

  @group-d
  Scenario: Test paths corresponding to EU persistent URI namespaces without a matching uuid
    Given I am on "data/dr8/non-existing-uiid"
    Then I should see the heading "Page not found"
    When I am on "data/w21/non-existing-uiid"
    Then I should see the heading "Page not found"

  @group-clone
  Scenario: eGovERA persistent URIs
    Given I am on "data/dr8/egovera/AccessRightsBusinessObject"
    Then I should see the heading "Access rights"
    And the url should match "/taxonomy/term/\d+"
