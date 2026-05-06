@api @group-g
Feature: SEO for news articles.
  As an owner of the website
  in order for my news to be better visible on the web
  I need proper metatag to be encapsulated in the html code.

  Scenario: Basic metatags are attached as JSON schema on the page.
    Given the following "collection" content:
      | title                                           |
      | Interoperable Europe Portal SEO news collection |
    And the following users:
      | Username                               | E-mail                 | First name | Family name |
      | Interoperable Europe Portal SEO author | joinup.seo@example.com | Kurk       | Smith       |
    And the following "news" content:
      | title                                | headline                                         | logo     | field_paragraphs_body | created                   | published at              | changed                   | author                                 | collection                                      | abstract |
      | Interoperable Europe Portal SEO news | Headline of Interoperable Europe Portal SEO news | logo.png | Body of news.         | Sun, 01 Dec 2019 13:00:00 | Wed, 25 Dec 2019 13:00:00 | Wed, 01 Jan 2020 13:00:00 | Interoperable Europe Portal SEO author | Interoperable Europe Portal SEO news collection |          |

    When I visit the "Interoperable Europe Portal SEO news" news
    Then the metatag JSON should be attached in the page
    And 1 metatag graph of type "http://schema.org/NewsArticle" should exist in the page
    And the metatag graph of the item with "name" "Interoperable Europe Portal SEO news" should have the following properties:
      | property            | value                                                                                                             |
      | @type               | http://schema.org/NewsArticle                                                                                     |
      | headline            | Headline of Interoperable Europe Portal SEO news                                                                  |
      | description         | Body of news.                                                                                                     |
      | isAccessibleForFree | True                                                                                                              |
      | datePublished       | 2019-12-25T13:00:00+0000                                                                                          |
      | dateModified        | 2020-01-01T13:00:00+0000                                                                                          |
      # __base_url__ will be replaced with the base url of the website.
      | mainEntityOfPage    | __base_url__/collection/interoperable-europe-portal-seo-news-collection/news/interoperable-europe-portal-seo-news |
    And the metatag graph of the item with "name" "Interoperable Europe Portal SEO news" should have the following "image" properties:
      | property             | value                         |
      | @type                | http://schema.org/ImageObject |
      | representativeOfPage | True                          |
      # The test files are assigned a random name so it is very hard to assert the real url. However, when the file is
      # found and added to the page, the dimensions are added as well. Asserting the dimensions also asserts the url in
      # a way.
      | width                | 377                           |
      | height               | 139                           |
    And the metatag graph of the item with "name" "Interoperable Europe Portal SEO news" should have the following "author" properties:
      | property | value                             |
      | @type    | http://schema.org/Person          |
      | @id      | __base_url__/user/__random_text__ |
      | name     | Kurk Smith                        |
      | url      | __base_url__/user/__random_text__ |
    And the metatag graph of the item with "name" "Interoperable Europe Portal SEO news" should have the following "publisher" properties:
      | property | value                             |
      | @type    | http://schema.org/Person          |
      | @id      | __base_url__/user/__random_text__ |
      | name     | Kurk Smith                        |
      | url      | __base_url__/user/__random_text__ |
    And the following meta tags should available in the html:
      | identifier             | value                                                                                                             |
      | description            | Body of news.                                                                                                     |
      | og:url                 | __base_url__/collection/interoperable-europe-portal-seo-news-collection/news/interoperable-europe-portal-seo-news |
      | og:site_name           | Interoperable Europe Portal                                                                                       |
      | og:title               | Interoperable Europe Portal SEO news                                                                              |
      | og:description         | Body of news.                                                                                                     |
      | og:image               | __base_url__/sites/default/files/__random_text__.png                                                              |
      | og:image:type          | image/png                                                                                                         |
      | og:image:width         | 377                                                                                                               |
      | og:image:height        | 139                                                                                                               |
      | article:author         | Kurk Smith                                                                                                        |
      | article:published_time | 2019-12-25T13:00:00+0000                                                                                          |
      | article:modified_time  | 2020-01-01T13:00:00+0000                                                                                          |

    When I visit the content overview page
    Then I should see the "Interoperable Europe Portal SEO news" tile
    # No metatags are defined for the keep up to date page.
    # No metatags JSON in general means also that the entity metatags of the news item
    # is also not attached when the tile is present.
    And the metatag JSON should not be attached in the page
