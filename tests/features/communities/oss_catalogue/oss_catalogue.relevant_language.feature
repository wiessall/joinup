@api @eu-oss-catalogue
Feature:
  In order to show the most data to visitors, EU OSS Catalogue provides a
  special equivalent of the OSS Solution's translatable fields which fetch and
  display the field values from the most relevant translation (where the most
  features are listed).

  Scenario: OSS solution translations are accessible to all users
    Given oss_solution multilingual content:
      | title    | status    | changed    | oss_source        | oss_release_date | langcode | default_translation | oss_features              | oss_long_description | oss_short_description | oss_screenshots | oss_git_url        | oss_publiccode_yml_content                                                                                                                 |
      | EN Title | published | 1690000000 | developers_italia | 2142-01-01       | en       | 1                   | EN Feature 1              | EN Long description  | EN Short description  |                 | https://github.com | !multiline!description:\n  en:\n    features:\n      0: EN Feature 1\n  it:\n    features:\n      0: IT Feature 1\n      1: IT Feature 2\n |
      | IT Title | ~~~       | 1690000000 | ~~~               | ~~~              | it       | 0                   | IT Feature 1,IT Feature 2 | IT Long description  | IT Short description  | IT file 1.jpg   | ~~~                | ~~~                                                                                                                                        |

    When I visit the "EN Title" "oss_solution"
    Then I should see the heading "EN Title"
    And I should see the following lines of text:
      | En Feature 1         |
      | EN Short description |
      | EN Long description  |
    But I should not see the following lines of text:
      | IT Feature 1         |
      | IT Feature 2         |
      | IT Short description |
      | IT Long description  |

    # Only the default (English) translation is advertised via hreflang. The
    # Italian translation is restricted for anonymous users, so its hreflang
    # link - and thus its URL - is not exposed to crawlers.
    # @see joinup_i18n_entity_access()
    Then I should see a "link[rel='alternate'][hreflang='en']" element

    # @wip: Anonymous still sees the English version. Adapt the test in
    # ISAICP-11420. Anonymous should also be able to see translated content.
    When I go to the "it" version of the "EN Title" oss_solution
    Then I should see the heading "EN Title"
    And I should see the following lines of text:
      | En Feature 1         |
      | EN Short description |
      | EN Long description  |
    But I should not see the following lines of text:
      | IT Feature 1         |
      | IT Feature 2         |
      | IT Short description |
      | IT Long description  |

    Given I am logged in as a user with the "moderator" role
    When I go to the "it" version of the "EN Title" oss_solution
    Then I should see the heading "IT Title"
    And I should see the following lines of text:
      | IT Feature 1         |
      | IT Feature 2         |
      | IT Short description |
      | IT Long description  |
    But I should not see the following lines of text:
      | En Feature 1         |
      | EN Short description |
      | EN Long description  |

    When I visit "/admin/content/eu-oss-catalogue/oss-solutions"
    Then I should see the heading "Open Source Software Solutions"
    And I should see the "EU OSS Catalogue administration" table
    And the "EU OSS Catalogue administration" table should contain the following columns:
      | Title    | URL                | Short description    | Source Catalogue  | Languages | Published | Changed            |
      | EN Title | https://github.com | EN Short description | Developers Italia | EN, IT    | Yes       | 22/07/2023 - 04:26 |

    # Ensures content translation edit forms can be accessed.
    Given I am logged in as a user with the "developer" role
    When I visit the "EN Title" "oss_solution"
    When I click "Translate" in the "Entity actions" region
    Then I should see the heading "Translations of EN Title"
    When I click "Edit" in the "English" row
    And I should see the heading "Edit Open Source Software Solution EN Title"
    Then the "Title" field should contain "EN Title"
    Then the "Short description" field should contain "EN Short description"
    Then the "Long description" field should contain "EN Long description"
    And I move backward one page
    When I click "Edit" in the "Italian" row
    And I should see the heading "Edit Open Source Software Solution IT Title [Italian translation]"
    Then the "Title" field should contain "IT Title"
    Then the "Short description" field should contain "IT Short description"
    Then the "Long description" field should contain "IT Long description"

  Scenario: Translatable fields are displayed based on the richest available translation
    Given oss_solution multilingual content:
      | title        | status    | changed    | oss_source        | oss_release_date | langcode | default_translation | oss_features              | oss_short_description | oss_publiccode_yml_content                                                                                                                                        |
      | ABC Solution | published | 1700000000 | developers_italia | 2025-10-19       | en       | 1                   |                           |                       | !multiline!description:\n  de:\n    features:\n      0: DE Feature 1\n      1: DE Feature 2\n  el:\n    features:\n      0: EL Feature 1\n      1: EL Feature 2\n |
      |              | ~~~       | ~~~        | ~~~               | ~~~              | de       | 0                   | DE Feature 1,DE Feature 2 | ~~~                   | ~~~                                                                                                                                                               |
      |              | ~~~       | ~~~        | ~~~               | ~~~              | el       | 0                   | EL Feature 1,EL Feature 2 | EL Short description  | ~~~                                                                                                                                                               |
    When I visit the "ABC Solution" "oss_solution"
    Then I should see the heading "ABC Solution"
    And I should not see the following lines of text:
      | EL Short description |
      | EL Feature 1         |
      | EL Feature 2         |
      | DE Feature 1         |
      | DE Feature 2         |

    Given I am logged in as a user with the "oss_catalogue_manager" role
    When I visit "/admin/content/eu-oss-catalogue/oss-solutions"
    Then I should see the heading "Open Source Software Solutions"
    And I should see the "EU OSS Catalogue administration" table
    And the "EU OSS Catalogue administration" table should contain the following columns:
      | Title        | URL | Short description | Source Catalogue  | Languages  | Published | Changed            |
      | ABC Solution |     |                   | Developers Italia | EN, DE, EL | Yes       | 14/11/2023 - 22:13 |
