@api @group-g
Feature:
  As the owner of the website
  In order for my site to be more appealing to the search engines
  I need to have basic metatags attached to the page.

  Scenario Outline: Front page metatags.
    Given I am <user type>
    When I am on the homepage
    Then the following meta tags should available in the html:
      | identifier    | value                                                                                                                                                                                                                      |
      | description   | The Interoperable Europe Portal is the European Union’s platform for promoting and supporting interoperability, collaboration, and knowledge sharing across public administrations, businesses, and citizens. It acts as a one-stop shop for discovering, sharing, and reusing IT solutions and good practices.            |
      | abstract      | Interoperable Europe Portal offers several services that aim to help e-Government professionals share their experience with each other. We also hope to support them to find, choose, re-use, develop and implement interoperability solutions. |
      | og:url        | __base_url__/                                                                                                                                                                                                              |
      | og:site_name  | Interoperable Europe Portal                                                                                                                                                                                                |
      | og:title      | Interoperable Europe Portal                                                                                                                                                                                                                     |
      | og:image      | __base_url__/themes/ventuno/assets/images/og-image.png                                                                                                                                                                     |
      | og:image:type | image/svg+xml                                                                                                                                                                                                              |
    And the HTML title of the page should be "Interoperable Europe Portal"

    When I visit the collection overview
    Then the following meta tags should available in the html:
      | identifier    | value                                                  |
      | og:url        | __base_url__/collections                               |
      | og:site_name  | Interoperable Europe Portal                            |
      | og:title      | Collections                                            |
      | og:image      | __base_url__/themes/ventuno/assets/images/og-image.png |
      | og:image:type | image/svg+xml                                          |

    Examples:
      | user type                                         |
      | an anonymous user                                 |
      | logged in as a user with the "authenticated" role |
