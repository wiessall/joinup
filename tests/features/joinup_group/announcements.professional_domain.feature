@api @group-e
Feature: Filter announcements by professional domain

  Scenario Outline: User can narrow down targeted users based on the business domains.
    Given the following <group>s:
      | title         | state     |
      | Pension funds | published |
    And users:
      | Username | E-mail              | Professional domain                     | Status  |
      | Mogul    | mogul@example.com   | Demography, E-health, E-justice, HR, HR | active  |
      | Greedy   | greedy@example.com  | Demography, E-health, E-justice         | active  |
      | Envious  | envy@example.com    | Demography, E-health                    | active  |
      | Coward   | coward@example.com  | Demography                              | active  |
      | Nick     | nick@example.com    | Demography                              | active  |
      | Mark     | mark@example.com    | Demography                              | active  |
      | Anthony  | anthony@example.com | Demography                              | active  |
      | Frank    | frank@example.com   | Demography                              | blocked |
    And the following <group> user membership:
      | <group>       | user    | state   |
      | Pension funds | Mogul   | active  |
      | Pension funds | Greedy  | active  |
      | Pension funds | Envious | active  |
      | Pension funds | Coward  | active  |
      | Pension funds | Nick    | active  |
      | Pension funds | Mark    | active  |
      | Pension funds | Anthony | blocked |
      | Pension funds | Frank   | active  |
    And the "Mark" user unsubscribes from "Pension funds" <group> announcements
    And the following legal document version:
      | Document     | Label | Published | Acceptance label       | Content                             |
      | Legal notice | 1.1   | yes       | I have read and accept | The information on this site is ... |
    And the following "Legal notice" acceptances:
      | Mogul   |
      | Greedy  |
      | Envious |
      | Coward  |
      # Nick has not accepted legal notice.
      | Mark    |
      | Anthony |
      | Frank   |
    And the following "Legal notice" acceptances:
      | Mogul  |
      | Greedy |
    And the following announcements:
      | group         | subject                             | body                            | state |
      | Pension funds | Demography announcement             | Body for Demography             | sent  |
      | Pension funds | E-health and E-justice announcement | Body for E-health and E-justice | sent  |

    Given I am logged in as a moderator

    When I go to the "Demography announcement" announcement of "Pension funds" <group>
    Then the following fields should be present "Professional domain filter"
    And the "Professional domain filter" field should contain the "E-health Dpt., HR Dpt., Law and Justice, Social and Political" option groups
    And the "Professional domain filter" select should contain the following options:
      | -E-health (3)   |
      | -HR (1)         |
      | -E-justice (2)  |
      | -Demography (5) |
    When I select "-Demography (5)" from "Professional domain filter"
    And I press "Approve"
    Then 4 e-mails should have been sent

    When I mark all emails as read
    And I go to the "E-health and E-justice announcement" announcement of "Pension funds" <group>
    And I select "-E-justice (2)" from "Professional domain filter"
    And I press "Approve"
    Then 2 e-mails should have been sent

    Examples:
      | group      |
      | collection |
      | solution   |
