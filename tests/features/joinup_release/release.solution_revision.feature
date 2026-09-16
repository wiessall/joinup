@api @group-d
Feature: Solution revisions referred by releases are never purged.

  Scenario: The revision purge queue keeps solution revisions referred by releases.
    When I am logged in as a moderator

    # Set the amount to 5 for the scope of the test.
    And I set the configuration item "node.type.solution" with key "third_party_settings.node_revision_delete.solution_revision_amount.settings.amount" to "5"
    And the following "collection" content:
      | title            |
      | Space technology |

    And the following "solution" content:
      | title       | description       | revision_log  | revision_timestamp | resources | collection       |
      | Rocket fuel | The first formula | First formula | 2020-01-01 10:00   | text.pdf  | Space technology |

    # Regression test for ISAICP-11817.
    And I visit the revisions page for "Rocket fuel"
    Then the "node revisions" table should contain the following columns:
      | Revision information                                        |
      | 01/01/2020 - 10:00 by Anonymous (not verified)First formula |

    And the following solution content revisions:
      | current title | description        | log message    | revision date    |
      | Rocket fuel   | The second formula | Second formula | 2020-01-02 10:00 |
      | Rocket fuel   | The third formula  | Third formula  | 2020-01-03 10:00 |

    # Refers to revision #3.
    And the following "release" content:
      | title          | release notes      | resources | release number | is version of |
      | Rocket fuel v1 | The first release. | text.pdf  | 1              | Rocket fuel   |

    And the following solution content revisions:
      | current title | description        | log message    | revision date    |
      | Rocket fuel   | The fourth formula | Fourth formula | 2020-01-04 10:00 |
      | Rocket fuel   | The fifth formula  | Fifth formula  | 2020-01-05 10:00 |
      | Rocket fuel   | The sixth formula  | Sixth formula  | 2020-01-06 10:00 |

    # Refers to revision #6.
    And the following "release" content:
      | title          | release notes       | resources | release number | is version of |
      | Rocket fuel v2 | The second release. | text.pdf  | 2              | Rocket fuel   |

    # Revisions #7 and #8. The latter is the active revision.
    And the following solution content revisions:
      | current title | description         | log message     | revision date    |
      | Rocket fuel   | The seventh formula | Seventh formula | 2020-01-07 10:00 |
      | Rocket fuel   | The eighth formula  | Eighth formula  | 2020-01-08 10:00 |

    # Revision #9 is a pending draft, so is never a candidate for deletion.
    And the following solution content revisions:
      | current title | description       | state                 | log message   | revision date    |
      | Rocket fuel   | The ninth formula | draft_after_published | Ninth formula | 2020-01-09 10:00 |
    Then the "Rocket fuel" solution content should have 9 revisions

    # All the revisions are listed in the revision overview page. Mind that the
    # revision log message is glued to the author name as they are rendered in
    # adjacent HTML elements.
    And I visit the revisions page for "Rocket fuel"
    Then the "node revisions" table should contain the following columns:
      | Revision information                                                                                        |
      | 09/01/2020 - 10:00 by Anonymous (not verified)Ninth formula                                                 |
      | 08/01/2020 - 10:00 by Anonymous (not verified)Eighth formula                                                |
      | 07/01/2020 - 10:00 by Anonymous (not verified)Seventh formula                                               |
      | 06/01/2020 - 10:00 by Anonymous (not verified)Sixth formula Revision referred by release(s): Rocket fuel v2 |
      | 05/01/2020 - 10:00 by Anonymous (not verified)Fifth formula                                                 |
      | 04/01/2020 - 10:00 by Anonymous (not verified)Fourth formula                                                |
      | 03/01/2020 - 10:00 by Anonymous (not verified)Third formula Revision referred by release(s): Rocket fuel v1 |
      | 02/01/2020 - 10:00 by Anonymous (not verified)Second formula                                                |
      | 01/01/2020 - 10:00 by Anonymous (not verified)First formula                                                 |

    # Saving the solution has queued it for revision purging.
    When I run the "node_revision_delete" queue worker

    # The `amount` setting keeps the active revision plus the newest 4 of the
    # revisions that are candidates for deletion. Revisions #3 and #6 are
    # referred by releases, so they are excluded from the candidate list and the
    # 4 slots are filled by revisions #7, #5, #4 and #2. Only revision #1 is
    # purged.
    Then the "Rocket fuel" solution content should have 8 revisions

    # All the revisions are still listed in the revision overview page, ...
    When I visit the revisions page for "Rocket fuel"
    Then the "node revisions" table should contain the following columns:
      | Revision information                                                                                        |
      | 09/01/2020 - 10:00 by Anonymous (not verified)Ninth formula                                                 |
      | 08/01/2020 - 10:00 by Anonymous (not verified)Eighth formula                                                |
      | 07/01/2020 - 10:00 by Anonymous (not verified)Seventh formula                                               |
      | 06/01/2020 - 10:00 by Anonymous (not verified)Sixth formula Revision referred by release(s): Rocket fuel v2 |
      | 05/01/2020 - 10:00 by Anonymous (not verified)Fifth formula                                                 |
      | 04/01/2020 - 10:00 by Anonymous (not verified)Fourth formula                                                |
      | 03/01/2020 - 10:00 by Anonymous (not verified)Third formula Revision referred by release(s): Rocket fuel v1 |
      | 02/01/2020 - 10:00 by Anonymous (not verified)Second formula                                                |

    # ... except the purged one.
    But the "node revisions" table should not contain the following columns:
      | Revision information                                        |
      | 01/01/2020 - 10:00 by Anonymous (not verified)First formula |

    # Revisions referred by releases are protected against manual deletion.
    # @covers \Drupal\joinup_release\Hook\ReleaseSolutionDataHooks::solutionRevisionDeleteAccess
    And I should not see the "Delete" in the "Third formula" row
    And I should not see the "Delete" in the "Sixth formula" row
    But I should see the "Delete" in the "Fourth formula" row
