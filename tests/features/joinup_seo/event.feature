@api @group-g
Feature: SEO for news articles.
  As an owner of the website
  in order for my news to be better visible on the web
  I need proper metatag to be encapsulated in the html code.

  Scenario Outline: Basic metatags are attached as JSON schema on the page.
    Given the following "collection" content:
      | title                       |
      | Joinup SEO event collection |
    And the following users:
      | Username          | E-mail                 | First name | Family name |
      | Joinup SEO author | joinup.seo@example.com | Patrick    | Stewart     |
    And the following "event" content:
      | title            | short title   | web url   | start date                | end date                  | body           | logo     | agenda        | location   | online location                                                                       | organisation        | scope         | keywords | collection                  | abstract |
      | Joinup SEO event | JOINUPSEO2020 | <web url> | Wed, 25 Dec 2019 13:00:00 | Wed, 01 Jan 2020 13:00:00 | Body of event. | logo.png | Event agenda. | <location> | title: "Some title", uri: "http://example.com/some-online-meetup"                     | European Commission | International | Alphabet | Joinup SEO event collection |          |
    When I visit the "Joinup SEO event" event
    Then the metatag JSON should be attached in the page
    And 1 metatag graph of type "http://schema.org/Event" should exist in the page
    And the metatag graph of the item with "name" "Joinup SEO event" should have the following properties:
      | property    | value                              |
      | @type       | http://schema.org/Event            |
      | name        | Joinup SEO event                   |
      # Though it would be nice to have this to the Joinup URL, Search Engines
      # expect this to be the URL of the event. If a website is provided, then
      # that means that the entity in Joinup is simply a promotion, and all the
      # handling of registrations etc, already has a website, thus we point to
      # that location.
      | url         | <expected url>                     |
      | description | Body of event.                     |
      # The __timezone__ variable will allow any 2-digit number in the string. This is because of different timezones in
      # different environments.
      | startDate   | 2019-12-25T__timezone__:00:00+0000 |
      | endDate     | 2020-01-01T__timezone__:00:00+0000 |
      # __base_url__ will be replaced with the base url of the website.
      | @id         | <expected url>                     |
    And the metatag graph of the item with "name" "Joinup SEO event" should have the following "image" properties:
      | property             | value                                                |
      | @type                | http://schema.org/ImageObject                        |
      | representativeOfPage | True                                                 |
      # __random_text__ can be any string that is appointed by the system and we
      # cannot predict. In this case it is the random file name of the image.
      | url                  | __base_url__/sites/default/files/__random_text__.png |
      | width                | 377                                                  |
      | height               | 139                                                  |
    And the metatag graph of the item with "name" "Joinup SEO event" should have the following "location" properties:
      | property | value                                 |
      | @type    | http://schema.org/Place               |
      | name     | <expected location>                   |
      | url      | http://example.com/some-online-meetup |
    # Target the location subgraph which has the name property set to "Rue
    # Belliard 28".
    And the metatag subgraph of the item with "name" "Rue Belliard 28" should have the following "address" properties:
      | property      | value                           |
      | @type         | http://schema.org/PostalAddress |
      | streetAddress | <expected location>             |
    And the metatag subgraph of the item with "name" "Rue Belliard 28" should have the following "geo" properties:
      | property  | value                            |
      | @type     | http://schema.org/GeoCoordinates |
      # Geo coordinates are hardcoded as offered by the service.
      | latitude  | 50.842291                        |
      | longitude | 4.370476                         |
    And the following meta tags should available in the html:
      | identifier      | value                                                                      |
      | description     | Body of event.                                                             |
      | og:url          | __base_url__/collection/joinup-seo-event-collection/event/joinup-seo-event |
      | og:site_name    | Interoperable Europe Portal                                                |
      | og:title        | Joinup SEO event                                                           |
      | og:description  | Body of event.                                                             |
      | og:image        | __base_url__/sites/default/files/__random_text__.png                       |
      | og:image:type   | image/png                                                                  |
      | og:image:width  | 377                                                                        |
      | og:image:height | 139                                                                        |

    When I visit the content overview page
    Then I should see the "Joinup SEO event" tile
    # No metatags are defined for the keep up to date page.
    # No metatags JSON in general means also that the entity metatags of the
    # news item is also not attached when the tile is present.
    And the metatag JSON should not be attached in the page

    Examples:
      | web url                                                       | expected url                                                               | location                           | expected location |
      |                                                               | __base_url__/collection/joinup-seo-event-collection/event/joinup-seo-event | Rue Belliard 28, Brussels, Belgium | Rue Belliard 28   |
      # Link cells must use named keys ('title' and 'uri') so the v6 entity
      # field parser recognises them; bare positional values are rejected.
      # @see: \Drupal\Driver\Core\Field\LinkHandler::expand
      | title: "Some url", uri: "http://some-random-event-url"        | http://some-random-event-url                                               | Rue Belliard 28, Brussels, Belgium | Rue Belliard 28   |

  Scenario: Events without physical address but with online location should still show the online location.
    Given the following "collection" content:
      | title                       |
      | Joinup SEO event collection |
    And the following users:
      | Username          | E-mail                 | First name | Family name |
      | Joinup SEO author | joinup.seo@example.com | Patrick    | Stewart     |
    And the following "event" content:
      | title            | short title   | online location                                                   | collection                  |
      | Joinup SEO event | JOINUPSEO2021 | title: "Some title", uri: "http://example.com/some-online-meetup" | Joinup SEO event collection |

    When I visit the "Joinup SEO event" event
    Then the metatag JSON should be attached in the page
    And 1 metatag graph of type "http://schema.org/Event" should exist in the page
    And the metatag graph of the item with "name" "Joinup SEO event" should have the following "location" properties:
      | property | value                                 |
      | @type    | http://schema.org/Place               |
      | url      | http://example.com/some-online-meetup |
