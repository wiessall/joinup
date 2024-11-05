@api @group-g
Feature:
  As the owner of the site
  In order to promote content to the search engines properly
  I need to have the proper sitemaps available.

  Scenario: Basic sitemap link availability.
    Given owner content:
      | name          | owner type |
      | Sitemap owner | Company    |
    And contact_information content:
      | name                | email                           |
      | Sitemap secretariat | sitemap.secretariat@example.com |
    And collection content:
      | title                        | state     |
      | Sitemap collection draft     | draft     |
      | Sitemap collection published | published |
    Given solution content:
      | title                      | description                 | owner         | contact information | collection                   | state     |
      | Sitemap solution draft     | Sitemap keywords everywhere | Sitemap owner | Sitemap secretariat | Sitemap collection published | draft     |
      | Sitemap solution published | Sitemap keywords everywhere | Sitemap owner | Sitemap secretariat | Sitemap collection published | published |
    And release content:
      | title             | release number | release notes | is version of              | state     |
      | Sitemap release 1 | 1              | New release   | Sitemap solution published | published |
      | Sitemap release 2 | 2              | Newer release | Sitemap solution published | draft     |
    And distribution content:
      | title                | description           | release           | access url | state     |
      | Sitemap distribution | Some kind of download | Sitemap release 1 | test.zip   | published |
    And licence content:
      | title           | description |
      | Sitemap licence | Not allowed |
    And "custom_page" content:
      | title                                           | collection                   | body | logo     | langcode | state     |
      | Sitemap custom page of draft                    | Sitemap collection draft     | N/A  | logo.png | en       | published |
      | Sitemap custom page of published                | Sitemap collection published | N/A  | logo.png | en       | published |
      | Sitemap custom page of published but in Spanish | Sitemap collection published | N/A  | logo.png | es       | published |
    And news content:
      | title                                    | headline     | body              | solution                   | state     | published at              |
      | Sitemap news draft                       | Sitemap news | Sitemap news body | Sitemap solution published | draft     |                           |
      | Sitemap news published and recent        | Sitemap news | Sitemap news body | Sitemap solution published | published | 1 day ago                 |
      | Sitemap news published but old           | Sitemap news | Sitemap news body | Sitemap solution published | published | 2 months ago              |
      | Sitemap news archived                    | Sitemap news | Sitemap news body | Sitemap solution published | archived  | 2 months ago              |
      | Sitemap news published but parent is not | Sitemap news | Sitemap news body | Sitemap solution draft     | published | Sun, 01 Dec 2019 13:00:00 |
    And event content:
      | title                                     | short title             | field_paragraphs_body     | agenda        | location   | organisation        | scope         | solution                   | state     |
      | Sitemap event draft                       | Sitemap event draft     | It will include fireworks | Event agenda. | Some place | European Commission | International | Sitemap solution published | draft     |
      | Sitemap event published                   | Sitemap event published | It will include fireworks | Event agenda. | Some place | European Commission | International | Sitemap solution published | published |
      | Sitemap event archived                    | Sitemap event archived  | It will include fireworks | Event agenda. | Some place | European Commission | International | Sitemap solution published | archived  |
      | Sitemap event published but parent is not | Sitemap event published | It will include fireworks | Event agenda. | Some place | European Commission | International | Sitemap solution draft     | published |
    And document content:
      | title                                        | document type | short title                | field_paragraphs_body | solution                   | state     |
      | Sitemap document draft                       | Document      | Sitemap document draft     | Read more...          | Sitemap solution published | draft     |
      | Sitemap document published                   | Document      | Sitemap document published | Read more...          | Sitemap solution published | published |
      | Sitemap document archived                    | Document      | Sitemap document archived  | Read more...          | Sitemap solution published | archived  |
      | Sitemap document published but parent is not | Document      | Sitemap document published | Read more...          | Sitemap solution draft     | published |
    And discussion content:
      | title                                          | body                  | solution                   | state     |
      | Sitemap discussion draft                       | Wanna discuss this??? | Sitemap solution published | draft     |
      | Sitemap discussion published                   | Wanna discuss this??? | Sitemap solution published | published |
      | Sitemap discussion archived                    | Wanna discuss this??? | Sitemap solution published | archived  |
      | Sitemap discussion published but parent is not | Wanna discuss this??? | Sitemap solution draft     | published |

    Given I run cron
    And I visit "/sitemap.xml"
    Then I should see the absolute urls of the following content entities:
      | Joinup     |
      | Sitemap collection published     |
      | Sitemap solution published       |
      | Sitemap custom page of published |
      | Sitemap discussion published     |
      | Sitemap discussion archived      |
      | Sitemap document published       |
      | Sitemap document archived        |
      | Sitemap event published          |
      | Sitemap event archived           |
      | Sitemap news published but old   |
      | Sitemap news archived            |
      | Sitemap release 1                |

    But I should not see the absolute urls of the following content entities:
      | Sitemap collection draft                        |
      | Sitemap solution draft                          |
      | Sitemap custom page of draft                    |
      | Sitemap custom page of published but in Spanish |
      | Sitemap discussion draft                        |
      | Sitemap discussion published but parent is not  |
      | Sitemap distribution                            |
      | Sitemap document draft                          |
      | Sitemap document published but parent is not    |
      | Sitemap event draft                             |
      | Sitemap event published but parent is not       |
      | Sitemap licence                                 |
      | Sitemap news draft                              |
      | Sitemap news published and recent               |
      | Sitemap news published but parent is not        |
      | Sitemap owner                                   |
      | Sitemap release 2                               |
      | Sitemap secretariat                             |
    # The following piece of xhtml is used to declare an alternative language. If it exists in the page it means that
    # another language is included in the output.
    And the response should not contain "<xhtml:link rel=\"alternate\" hreflang="

    # Check the dedicated sitemap for news articles that only contains hot news
    # topics published in the last 2 days.
    When I visit "/news/sitemap.xml"
    And I should see the absolute urls of the following content entities:
      | Sitemap news published and recent |

    But I should not see the absolute urls of the following content entities:
      | Sitemap collection draft                       |
      | Sitemap collection published                   |
      | Sitemap solution draft                         |
      | Sitemap solution published                     |
      | Sitemap custom page of draft                   |
      | Sitemap discussion draft                       |
      | Sitemap discussion published                   |
      | Sitemap discussion archived                    |
      | Sitemap discussion published but parent is not |
      | Sitemap distribution                           |
      | Sitemap document draft                         |
      | Sitemap document published                     |
      | Sitemap document archived                      |
      | Sitemap document published but parent is not   |
      | Sitemap event draft                            |
      | Sitemap event published                        |
      | Sitemap event archived                         |
      | Sitemap event published but parent is not      |
      | Sitemap licence                                |
      | Sitemap news draft                             |
      | Sitemap news published but old                 |
      | Sitemap news archived                          |
      | Sitemap news published but parent is not       |
      | Sitemap owner                                  |
      | Sitemap release 1                              |
      | Sitemap release 2                              |
      | Sitemap secretariat                            |

    # Simulate that 2 days have passed since last time the sitemaps have been created.
    When the publication date of the "Sitemap news published and recent" news content is changed to "3 days ago"
    And I run cron
    # Assert that the "Sitemap news published and recent" news article is no
    # longer considered as a hot topic and has moved to the standard sitemap.
    And I visit "/sitemap.xml"
    Then I should see the absolute urls of the following content entities:
      | Sitemap collection published      |
      | Sitemap solution published        |
      | Sitemap custom page of published  |
      | Sitemap discussion published      |
      | Sitemap discussion archived       |
      | Sitemap document published        |
      | Sitemap document archived         |
      | Sitemap event published           |
      | Sitemap event archived            |
      | Sitemap news published and recent |
      | Sitemap news published but old    |
      | Sitemap news archived             |
      | Sitemap release 1                 |

    But I should not see the absolute urls of the following content entities:
      | Sitemap collection draft                       |
      | Sitemap solution draft                         |
      | Sitemap custom page of draft                   |
      | Sitemap discussion draft                       |
      | Sitemap discussion published but parent is not |
      | Sitemap distribution                           |
      | Sitemap document draft                         |
      | Sitemap document published but parent is not   |
      | Sitemap event draft                            |
      | Sitemap event published but parent is not      |
      | Sitemap licence                                |
      | Sitemap news draft                             |
      | Sitemap news published but parent is not       |
      | Sitemap owner                                  |
      | Sitemap release 2                              |
      | Sitemap secretariat                            |

    # The hot news article is now removed from the news sitemap, and the page
    # should no longer be present since it doesn't contain any more entries.
    When I go to "/news/sitemap.xml"
    Then the response status code should be 404
