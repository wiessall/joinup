@api @group-g
Feature: Subscribing to community content in collections and solutions
  As an avid fan of Interoperable Europe Portal
  I want to receive a periodic digest listing newly published content
  So that I can stay informed about everything

  Background:
    Given the following "collection" content:
      | title              |
      | Black hole imaging |
    Given the following "solution" content:
      | title                 | collection         |
      | Null geodesics        | Black hole imaging |
      | Bolometric appearance | Black hole imaging |
    And users:
      | Username  | E-mail               | First name  | Family name | Notification frequency |
      | jpluminet | jpluminet@example.fr | Jean-Pierre | Luminet     | daily                  |
      | junfukue  | jun.fukue@example.jp | Jun         | Fukue       | weekly                 |
    And the following collection user memberships:
      | collection         | user      | roles |
      | Black hole imaging | jpluminet |       |
    And the following solution user memberships:
      | solution              | user      | roles |
      | Null geodesics        | jpluminet |       |
      | Bolometric appearance | jpluminet |       |
    And the following collection content subscriptions:
      | collection         | user      | subscriptions                               |
      | Black hole imaging | jpluminet | discussion, document, event, news, solution |
    And the following solution content subscriptions:
      | solution              | user      | subscriptions                     |
      | Null geodesics        | jpluminet | discussion, document, event, news |
      | Bolometric appearance | jpluminet | discussion, document, event, news |

    And all message digests have been delivered

  Scenario: Receive a digest of community content that is published
    Given the following "discussion" content:
      | title                  | body                               | collection         | solution       | state     | author   |
      | Active galactic nuclei | A thin relativistic accretion disk | Black hole imaging |                | published | junfukue |
      | Light diffusion        | Photons emitted at constant radius |                    | Null geodesics | archived  | junfukue |
    And the following "document" content:
      | title               | abstract         | field_paragraphs_body           | collection         | solution              | author   |
      | Doppler's effect    |                  | Caused by disk rotation         | Black hole imaging |                       | junfukue |
      | Distant observers   | Distant abstract | Distribution of bolometric flux |                    | Bolometric appearance | junfukue |
      | Close observers     |                  | Close observers body            |                    | Bolometric appearance | junfukue |
    And the following "event" content:
      | title               | field_paragraphs_body | collection         | solution       | author   | start date          | end date            | abstract |
      | Effective potential | Schwarzschild metric  | Black hole imaging |                | junfukue | 2019-11-28T11:12:13 | 2019-11-28T11:12:13 |          |
      | Deflected rays      | Marginally trapped    |                    | Null geodesics | junfukue | 2019-12-05T12:00:00 | 2019-12-15T12:00:00 |          |
    And the following "news" content:
      | title             | field_paragraphs_body      | collection         | solution              | state     | author | abstract |
      | The periastron    | Jacobian elliptic integral | Black hole imaging |                       | published | bisera |          |
      | Newtonian context | Projecting ellipses        |                    | Bolometric appearance | archived  | hristo |          |

    # The group content digest should contain the content posted in the
    # collection and the solution.
    Then the daily group content subscription digest for jpluminet should match the following messages:
      | Active galactic nuclei |
      | Doppler's effect       |
      | Effective potential    |
      | The periastron         |
      | Close observers        |
      | Distant observers      |
      | Deflected rays         |

    # Check that the message is formatted correctly. It should contain the
    # groups in alphabetical order, underneath which the group content is shown,
    # also in alphabetical order.
    Given all message digests have been delivered
    Then the group content subscription digest sent to jpluminet contains the following sections:
      | title                  |
      | Black hole imaging     |
      | Active galactic nuclei |
      | Doppler's effect       |
      | Effective potential    |
      | The periastron         |
      | Bolometric appearance  |
      | Close observers        |
      | Distant observers      |
      | Null geodesics         |
      | Deflected rays         |

    And the email sent to jpluminet with subject "Interoperable Europe Portal: Daily digest message" contains the following lines of text:
      | Dear Jean-Pierre,                                                                             |
      | New content published in Black hole imaging                                                   |
      | Active galactic nuclei                                                                        |
      | A thin relativistic accretion disk                                                            |
      | Read more                                                                                     |
      | Doppler's effect                                                                              |
      | Caused by disk rotation                                                                       |
      | Read more                                                                                     |
      | 28 November 2019                                                                              |
      | Effective potential                                                                           |
      | Schwarzschild metric                                                                          |
      | Read more                                                                                     |
      | The periastron                                                                                |
      | Jacobian elliptic integral                                                                    |
      | Read more                                                                                     |
      | New content published in Bolometric appearance                                                |
      | Distant observers                                                                             |
      | Distant abstract                                                                              |
      | Read more                                                                                     |
      | Close observers                                                                               |
      | Close observers body                                                                          |
      | Read more                                                                                     |
      | New content published in Null geodesics                                                       |
      | 05 to 15 December 2019                                                                        |
      | Deflected rays                                                                                |
      | Marginally trapped                                                                            |
      | Read more                                                                                     |
      | Kind regards,                                                                                 |
      | The Portal Support Team                                                                       |
      | You received this email because you opted in for Interoperable Europe Portal digest messages. |
      | Manage your subscription notifications or unsubscribe from all.                               |
