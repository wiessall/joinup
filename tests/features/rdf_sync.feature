@api
Feature: RDF synchronization of contents.
  As an owner of the website
  in order for my news to be better visible on the web
  I need my RDF entities to be properly synchronized with the site contents.

  Scenario: ADMS basic classes are properly exported.
    Given users:
      | Username     | E-mail           |
      | Owner of all | blah@example.com |
    And contact_information content:
      | uri                         | name            | email                       | website url        |
      | http://example.com/contact1 | Syncing contact | syncing.contact@example.com | http://example.com |
    And owner_type terms:
      | uri                            | name       |
      | http://example.com/owner-type1 | University |
    And owner content:
      | uri                       | name          | owner type |
      | http://example.com/owner1 | Syncing owner | University |
    And licence content:
      | uri                         | title           | description       | licence type  |
      | http://example.com/licence1 | Syncing licence | Some nice licence | Public domain |
    And spatial terms:
      | uri                         | name     |
      | http://example.com/country1 | Atlantis |
    And language terms:
      | uri                          | name     |
      | http://example.com/language1 | Atlantic |
      | http://example.com/language2 | Aramaic  |
    And file_type terms:
      | uri                      | name    |
      | http://example.com/type1 | Archive |
    And representation_technique terms:
      | uri                      | name   |
      | http://example.com/tech1 | Schema |
    And collection content:
      | uri                            | title              | author       | abstract  | description                    | access url                  | logo     | contact information | owner         | creation date    | published at     | modification date | closed | content creation         | moderation | topic      | spatial coverage | state     | featured |
      | http://example.com/collection1 | Syncing collection | Owner of all | Abstract. | Description of the collection. | http://www.example.com/msm/ | logo.png | Syncing contact     | Syncing owner | 2016-06-13T13:00 | 2016-07-13T13:00 | 2017-06-30T11:27  | no     | facilitators and authors | no         | Demography | Atlantis         | published | no       |
      | http://example.com/collection2 | Syncing collection | Owner of all | Abstract. | Description of the collection. | http://www.example.com/msm/ | logo.png | Syncing contact     | Syncing owner | 2016-06-13T13:00 | 2016-07-13T13:00 | 2017-06-30T11:27  | no     | facilitators and authors | no         | Demography | Atlantis         | draft     | no       |
    Given solution content:
      | uri                          | title            | collection         | author       | description    | logo     | owner         | contact information | creation date   | published at    | modification date | documentation | content creation | keywords         | landing page                    | language | metrics page                    | moderation | topic                    | related solutions | solution type                      | spatial coverage | status    | state     | featured |
      | http://example.com/solution1 | Syncing solution | Syncing collection | Owner of all | <p>content</p> | logo.png | Syncing owner | Syncing contact     | 2017-10-01T8:00 | 2017-11-01T8:00 | 2017-12-01T8:43   | text.pdf      | registered users | ADMS, validation | http://www.example.com/landing1 | Atlantic | http://www.example.org/metrics1 | no         | EU and European Policies |                   | Networking Infrastructure Enablers | Atlantis         | Completed | published | no       |
    And release content:
      | uri                         | title           | documentation | release number | release notes | creation date    | published at     | changed          | is version of    | state     | status    | spatial coverage | keywords | language |
      | http://example.com/release1 | Syncing release | text.pdf      | 3.0.0          | New 3.0       | 2017-11-10T11:11 | 2017-11-11T11:11 | 2017-11-12T11:11 | Syncing solution | published | Completed | Atlantis         | food     | Atlantic |
      # Draft release should not be synced.
      | http://example.com/release2 | Draft release   | text.pdf      | 3.0.0          | New 3.0       | 2017-11-10T11:11 | 2017-11-11T11:11 | 2017-11-12T11:11 | Syncing solution | draft     | Completed | Atlantis         | food     | Atlantic |

    # The two cases cover the two possible access URL types extracted.
    # @see \Drupal\joinup_distribution\EventSubscriber\DistributionParentRdfSyncSubscriber::addDistributionParentRelation().
    And distribution content:
      | uri                              | title                   | description                            | creation date    | publication date | changed          | access url                            | release         | solution         | downloads | licence         | format  | status    | representation technique | state     |
      | http://example.com/distribution1 | SyncingDistribution.zip | The zipped version of Syncing release. | 2017-01-11T11:20 | 2017-02-11T11:20 | 2017-03-11T11:20 | http://www.example.org/release.zip    | Syncing release |                  | 232       | Syncing licence | Archive | Completed | Schema                   | published |
      | http://example.com/distribution2 | SyncingDistribution.rdf | The RDF version of Syncing release.    | 2017-04-11T11:20 | 2017-05-11T11:20 | 2017-06-11T11:20 | http://www.example.org/some_other.zip | Syncing release |                  | 232       | Syncing licence | Archive | Completed | Schema                   | published |
      | http://example.com/distribution3 | SyncingSolution.zip 3   | The ZIP version of Syncing solution 3. | 2017-07-11T11:20 | 2017-08-11T11:20 | 2017-09-11T11:20 | http://www.example.org/solution.zip   |                 | Syncing solution | 232       | Syncing licence | Archive | Completed | Schema                   | published |
      # Draft distributions should not be synced.
      | http://example.com/distribution4 | SyncingSolution.zip 4   | Dummy text.                            | 2017-07-11T11:20 | 2017-08-11T11:20 | 2017-09-11T11:20 | http://www.example.org/solution.zip   | Syncing release |                  | 232       | Syncing licence | Archive | Completed | Schema                   | draft     |
      | http://example.com/distribution5 | SyncingSolution.zip 5   | Dummy text.                            | 2017-07-11T11:20 | 2017-08-11T11:20 | 2017-09-11T11:20 | http://www.example.org/solution.zip   |                 | Syncing solution | 232       | Syncing licence | Archive | Completed | Schema                   | draft     |

    # Check initial exported data.
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                               |
      | http://example.com/contact1 | http://www.w3.org/2006/vcard/ns#fn              | Syncing contact                      |
      | http://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasEmail        | syncing.contact@example.com          |
      | http://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasURL          | http://example.com                   |
      | http://example.com/contact1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/2006/vcard/ns#Kind |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                   | predicate                                       | object                          |
      | http://example.com/owner1 | http://xmlns.com/foaf/0.1/name                  | Syncing owner                   |
      | http://example.com/owner1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://xmlns.com/foaf/0.1/Agent |
      | http://example.com/owner1 | http://purl.org/dc/terms/type                   | http://example.com/owner-type1  |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                                   |
      | http://example.com/licence1 | http://www.w3.org/2000/01/rdf-schema#label      | Syncing licence                          |
      | http://example.com/licence1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/dc/terms/LicenseDocument |
      | http://example.com/licence1 | http://purl.org/dc/terms/type                   | http://purl.org/adms/licence/PD          |
      | http://example.com/licence1 | http://purl.org/dc/terms/description            | Some nice licence                        |
      # Default value for the deprecated field.
      | http://example.com/licence1 | http://joinup.eu/licence/deprecated             | 0                                        |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                        | predicate                                       | object                            |
      | http://example.com/collection1 | http://purl.org/dc/terms/title                  | Syncing collection                |
      | http://example.com/collection1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Catalog |
      | http://example.com/collection1 | http://purl.org/dc/terms/description            | Description of the collection.    |
      | http://example.com/collection1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1       |
      | http://example.com/collection1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1         |
      # The `published_at` property is exported as `issued` in the RDF.
      | http://example.com/collection1 | http://purl.org/dc/terms/issued                 | 2016-07-13T13:00:00Z              |
      | http://example.com/collection1 | http://purl.org/dc/terms/modified               | 2017-06-30T11:27:00Z              |
      # The link to the solution is exported by the
      # \Drupal\joinup_solution\EventSubscriber\SolutionRdfSyncSubscriber::addSolutionLinkToCollection.
      | http://example.com/collection1 | http://www.w3.org/ns/dcat#dataset               | http://example.com/solution1      |
      | http://example.com/collection1 | http://xmlns.com/foaf/0.1/homepage              | http://www.example.com/msm/       |
      | http://example.com/collection1 | http://purl.org/dc/terms/spatial                | http://example.com/country1       |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                                     |
      | http://example.com/solution1 | http://purl.org/dc/terms/title                  | Syncing solution                                           |
      | http://example.com/solution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset                          |
      | http://example.com/solution1 | http://purl.org/dc/terms/description            | <p>content</p>                                             |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#keyword               | ADMS                                                       |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#keyword               | validation                                                 |
      | http://example.com/solution1 | http://purl.org/dc/terms/language               | http://example.com/language1                               |
      | http://example.com/solution1 | http://purl.org/dc/terms/hasVersion             | http://example.com/release1                                |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#distribution          | http://example.com/distribution3                           |
      # The `published_at` property is exported as `issued` in the RDF.
      | http://example.com/solution1 | http://purl.org/dc/terms/issued                 | 2017-11-01T08:00:00Z                                       |
      | http://example.com/solution1 | http://purl.org/dc/terms/modified               | 2017-12-01T08:43:00Z                                       |
      | http://example.com/solution1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1                                  |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1                                |
      | http://example.com/solution1 | http://purl.org/dc/terms/type                   | http://data.europa.eu/dr8/NetworkingInfrastructureEnablers |
      | http://example.com/solution1 | http://purl.org/dc/terms/spatial                | http://example.com/country1                                |
    And the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                                                     |
      | http://example.com/release1 | http://purl.org/dc/terms/title                  | Syncing release                                            |
      | http://example.com/release1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset                          |
      | http://example.com/release1 | http://www.w3.org/2002/07/owl#versionInfo       | 3.0.0                                                      |
      | http://example.com/release1 | http://purl.org/dc/terms/description            | <p>content</p>                                             |
      | http://example.com/release1 | http://purl.org/dc/terms/issued                 | 2017-11-11T11:11:00Z                                       |
      | http://example.com/release1 | http://purl.org/dc/terms/modified               | 2017-11-12T11:11:00Z                                       |
      | http://example.com/release1 | http://www.w3.org/ns/adms#status                | http://purl.org/adms/status/Completed                      |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#keyword               | food                                                       |
      | http://example.com/release1 | http://purl.org/dc/terms/isVersionOf            | http://example.com/solution1                               |
      | http://example.com/release1 | http://www.w3.org/ns/adms#versionNotes          | New 3.0                                                    |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#distribution          | http://example.com/distribution1                           |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#distribution          | http://example.com/distribution2                           |
      # Inherited from solution.
      | http://example.com/release1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1                                  |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1                                |
      | http://example.com/release1 | http://purl.org/dc/terms/type                   | http://data.europa.eu/dr8/NetworkingInfrastructureEnablers |
      | http://example.com/release1 | http://purl.org/dc/terms/language               | http://example.com/language1                               |
      | http://example.com/release1 | http://purl.org/dc/terms/spatial                | http://example.com/country1                                |
    And the following "http://data" graph triples exist in the RDF backend:
      | subject                          | predicate                                         | object                                 |
      | http://example.com/distribution1 | http://purl.org/dc/terms/title                    | SyncingDistribution.zip                |
      | http://example.com/distribution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type   | http://www.w3.org/ns/dcat#Distribution |
      | http://example.com/distribution1 | http://purl.org/dc/terms/description              | The zipped version of Syncing release. |
      | http://example.com/distribution1 | http://purl.org/dc/terms/issued                   | 2017-02-11T11:20:00Z                   |
      | http://example.com/distribution1 | http://purl.org/dc/terms/modified                 | 2017-03-11T11:20:00Z                   |
      | http://example.com/distribution1 | http://www.w3.org/ns/dcat#accessURL               | http://www.example.org/release.zip     |
      | http://example.com/distribution1 | http://purl.org/dc/terms/format                   | http://example.com/type1               |
      | http://example.com/distribution1 | http://www.w3.org/ns/adms#status                  | http://purl.org/adms/status/Completed  |
      | http://example.com/distribution1 | http://www.w3.org/ns/adms#representationTechnique | http://example.com/tech1               |
      | http://example.com/distribution1 | http://purl.org/dc/terms/license                  | http://example.com/licence1            |
      | http://example.com/distribution1 | http://www.w3.org/ns/dcat#accessURL               | http://www.example.org/release.zip     |
    And the following "http://data" graph triples exist in the RDF backend:
      | subject                          | predicate                                         | object                                 |
      | http://example.com/distribution2 | http://purl.org/dc/terms/title                    | SyncingDistribution.rdf                |
      | http://example.com/distribution2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type   | http://www.w3.org/ns/dcat#Distribution |
      | http://example.com/distribution2 | http://purl.org/dc/terms/description              | The RDF version of Syncing release.    |
      | http://example.com/distribution2 | http://purl.org/dc/terms/issued                   | 2017-05-11T11:20:00Z                   |
      | http://example.com/distribution2 | http://purl.org/dc/terms/modified                 | 2017-06-11T11:20:00Z                   |
      | http://example.com/distribution2 | http://www.w3.org/ns/dcat#accessURL               | http://www.example.org/some_other.zip  |
      | http://example.com/distribution2 | http://purl.org/dc/terms/format                   | http://example.com/type1               |
      | http://example.com/distribution2 | http://www.w3.org/ns/adms#status                  | http://purl.org/adms/status/Completed  |
      | http://example.com/distribution2 | http://www.w3.org/ns/adms#representationTechnique | http://example.com/tech1               |
      | http://example.com/distribution2 | http://purl.org/dc/terms/license                  | http://example.com/licence1            |

    # Collection update.
    When I update the entity having "http://example.com/collection1" as RDF resource:
      | title       | Syncing collection updated |
      | changed     | 2017-06-30T11:27:00Z       |
      | field_state | draft                      |
    # Since the new version is not published, data should not change.
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                        | predicate                                       | object                            |
      | http://example.com/collection1 | http://purl.org/dc/terms/title                  | Syncing collection                |
      | http://example.com/collection1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Catalog |
      | http://example.com/collection1 | http://purl.org/dc/terms/description            | Description of the collection.    |
      | http://example.com/collection1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1       |
      | http://example.com/collection1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1         |
      | http://example.com/collection1 | http://purl.org/dc/terms/issued                 | 2016-07-13T13:00:00Z              |
      | http://example.com/collection1 | http://purl.org/dc/terms/modified               | 2017-06-30T11:27:00Z              |
      | http://example.com/collection1 | http://www.w3.org/ns/dcat#dataset               | http://example.com/solution1      |
      | http://example.com/collection1 | http://xmlns.com/foaf/0.1/homepage              | http://www.example.com/msm/       |
      | http://example.com/collection1 | http://purl.org/dc/terms/spatial                | http://example.com/country1       |
    # Publish the changes.
    When I update the entity having "http://example.com/collection1" as RDF resource:
      | changed     | 2017-06-30T11:27:00Z |
      | field_state | published            |
    # The collection is now published.
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                        | predicate                                       | object                            |
      | http://example.com/collection1 | http://purl.org/dc/terms/title                  | Syncing collection updated        |
      | http://example.com/collection1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Catalog |
      | http://example.com/collection1 | http://purl.org/dc/terms/description            | Description of the collection.    |
      | http://example.com/collection1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1       |
      | http://example.com/collection1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1         |
      | http://example.com/collection1 | http://purl.org/dc/terms/issued                 | 2016-07-13T13:00:00Z              |
      | http://example.com/collection1 | http://purl.org/dc/terms/modified               | 2017-06-30T11:27:00Z              |
      | http://example.com/collection1 | http://www.w3.org/ns/dcat#dataset               | http://example.com/solution1      |
      | http://example.com/collection1 | http://xmlns.com/foaf/0.1/homepage              | http://www.example.com/msm/       |
      | http://example.com/collection1 | http://purl.org/dc/terms/spatial                | http://example.com/country1       |

    # Solution update.
    When I update the entity having "http://example.com/solution1" as RDF resource:
      | title       | Syncing solution updated |
      | changed     | 2017-12-01T8:43:00Z      |
      | field_state | draft                    |
    # Since the new version is not published, data should not change.
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                                     |
      | http://example.com/solution1 | http://purl.org/dc/terms/title                  | Syncing solution                                           |
      | http://example.com/solution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset                          |
      | http://example.com/solution1 | http://purl.org/dc/terms/description            | <p>content</p>                                             |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#keyword               | ADMS                                                       |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#keyword               | validation                                                 |
      | http://example.com/solution1 | http://purl.org/dc/terms/language               | http://example.com/language1                               |
      | http://example.com/solution1 | http://purl.org/dc/terms/hasVersion             | http://example.com/release1                                |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#distribution          | http://example.com/distribution3                           |
      | http://example.com/solution1 | http://purl.org/dc/terms/issued                 | 2017-11-01T08:00:00Z                                       |
      | http://example.com/solution1 | http://purl.org/dc/terms/modified               | 2017-12-01T08:43:00Z                                       |
      | http://example.com/solution1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1                                  |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1                                |
      | http://example.com/solution1 | http://purl.org/dc/terms/type                   | http://data.europa.eu/dr8/NetworkingInfrastructureEnablers |
      | http://example.com/solution1 | http://purl.org/dc/terms/spatial                | http://example.com/country1                                |
    # Publish the changes.
    When I update the entity having "http://example.com/solution1" as RDF resource:
      | changed     | 2017-12-01T8:43:00Z |
      | field_state | published           |
    # The solution is now published.
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                                     |
      | http://example.com/solution1 | http://purl.org/dc/terms/title                  | Syncing solution updated                                   |
      | http://example.com/solution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset                          |
      | http://example.com/solution1 | http://purl.org/dc/terms/description            | <p>content</p>                                             |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#keyword               | ADMS                                                       |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#keyword               | validation                                                 |
      | http://example.com/solution1 | http://purl.org/dc/terms/language               | http://example.com/language1                               |
      | http://example.com/solution1 | http://purl.org/dc/terms/hasVersion             | http://example.com/release1                                |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#distribution          | http://example.com/distribution3                           |
      | http://example.com/solution1 | http://purl.org/dc/terms/issued                 | 2017-11-01T08:00:00Z                                       |
      | http://example.com/solution1 | http://purl.org/dc/terms/modified               | 2017-12-01T08:43:00Z                                       |
      | http://example.com/solution1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1                                  |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1                                |
      | http://example.com/solution1 | http://purl.org/dc/terms/type                   | http://data.europa.eu/dr8/NetworkingInfrastructureEnablers |
      | http://example.com/solution1 | http://purl.org/dc/terms/spatial                | http://example.com/country1                                |

    # Release update.
    When I update the entity having "http://example.com/release1" as RDF resource:
      | title       | Syncing release - revision |
      | changed     | 2019-12-01T08:44:00Z       |
      | field_state | draft                      |
    # Since the new version is not published, data should not change.
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                                                     |
      | http://example.com/release1 | http://purl.org/dc/terms/title                  | Syncing release                                            |
      | http://example.com/release1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset                          |
      | http://example.com/release1 | http://www.w3.org/2002/07/owl#versionInfo       | 3.0.0                                                      |
      | http://example.com/release1 | http://purl.org/dc/terms/description            | <p>content</p>                                             |
      | http://example.com/release1 | http://purl.org/dc/terms/issued                 | 2017-11-11T11:11:00Z                                       |
      | http://example.com/release1 | http://purl.org/dc/terms/modified               | 2017-11-12T11:11:00Z                                       |
      | http://example.com/release1 | http://www.w3.org/ns/adms#status                | http://purl.org/adms/status/Completed                      |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#keyword               | food                                                       |
      | http://example.com/release1 | http://purl.org/dc/terms/isVersionOf            | http://example.com/solution1                               |
      | http://example.com/release1 | http://www.w3.org/ns/adms#versionNotes          | New 3.0                                                    |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#distribution          | http://example.com/distribution1                           |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#distribution          | http://example.com/distribution2                           |
      # Inherited from solution.
      | http://example.com/release1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1                                  |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1                                |
      | http://example.com/release1 | http://purl.org/dc/terms/type                   | http://data.europa.eu/dr8/NetworkingInfrastructureEnablers |
      | http://example.com/release1 | http://purl.org/dc/terms/language               | http://example.com/language1                               |
      | http://example.com/release1 | http://purl.org/dc/terms/spatial                | http://example.com/country1                                |
    # Publish the changes.
    When I update the entity having "http://example.com/release1" as RDF resource:
      | changed     | 2019-12-02T08:44:00Z |
      | field_state | published            |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                                                     |
      | http://example.com/release1 | http://purl.org/dc/terms/title                  | Syncing release - revision                                 |
      | http://example.com/release1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset                          |
      | http://example.com/release1 | http://www.w3.org/2002/07/owl#versionInfo       | 3.0.0                                                      |
      | http://example.com/release1 | http://purl.org/dc/terms/description            | <p>content</p>                                             |
      | http://example.com/release1 | http://purl.org/dc/terms/issued                 | 2017-11-11T11:11:00Z                                       |
      | http://example.com/release1 | http://purl.org/dc/terms/modified               | 2019-12-02T08:44:00Z                                       |
      | http://example.com/release1 | http://www.w3.org/ns/adms#status                | http://purl.org/adms/status/Completed                      |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#keyword               | food                                                       |
      | http://example.com/release1 | http://purl.org/dc/terms/isVersionOf            | http://example.com/solution1                               |
      | http://example.com/release1 | http://www.w3.org/ns/adms#versionNotes          | New 3.0                                                    |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#distribution          | http://example.com/distribution1                           |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#distribution          | http://example.com/distribution2                           |
      # Inherited from solution.
      | http://example.com/release1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1                                  |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1                                |
      | http://example.com/release1 | http://purl.org/dc/terms/type                   | http://data.europa.eu/dr8/NetworkingInfrastructureEnablers |
      | http://example.com/release1 | http://purl.org/dc/terms/language               | http://example.com/language1                               |
      | http://example.com/release1 | http://purl.org/dc/terms/spatial                | http://example.com/country1                                |

    # Distribution update.
    When I update the entity having "http://example.com/distribution1" as RDF resource:
      | title       | SyncingDistribution - revision |
      | changed     | 2019-12-01T08:44:00Z           |
      | field_state | draft                          |
    # Since the new version is not published, data should not change.
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                          | predicate                                         | object                                 |
      | http://example.com/distribution1 | http://purl.org/dc/terms/title                    | SyncingDistribution.zip                |
      | http://example.com/distribution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type   | http://www.w3.org/ns/dcat#Distribution |
      | http://example.com/distribution1 | http://purl.org/dc/terms/description              | The zipped version of Syncing release. |
      | http://example.com/distribution1 | http://purl.org/dc/terms/issued                   | 2017-02-11T11:20:00Z                   |
      | http://example.com/distribution1 | http://purl.org/dc/terms/modified                 | 2017-03-11T11:20:00Z                   |
      | http://example.com/distribution1 | http://www.w3.org/ns/dcat#accessURL               | http://www.example.org/release.zip     |
      | http://example.com/distribution1 | http://purl.org/dc/terms/format                   | http://example.com/type1               |
      | http://example.com/distribution1 | http://www.w3.org/ns/adms#status                  | http://purl.org/adms/status/Completed  |
      | http://example.com/distribution1 | http://www.w3.org/ns/adms#representationTechnique | http://example.com/tech1               |
      | http://example.com/distribution1 | http://purl.org/dc/terms/license                  | http://example.com/licence1            |
      | http://example.com/distribution1 | http://www.w3.org/ns/dcat#accessURL               | http://www.example.org/release.zip     |
    # Publish the changes.
    When I update the entity having "http://example.com/distribution1" as RDF resource:
      | changed     | 2019-12-02T08:44:00Z |
      | field_state | published            |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                          | predicate                                         | object                                 |
      | http://example.com/distribution1 | http://purl.org/dc/terms/title                    | SyncingDistribution - revision         |
      | http://example.com/distribution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type   | http://www.w3.org/ns/dcat#Distribution |
      | http://example.com/distribution1 | http://purl.org/dc/terms/description              | The zipped version of Syncing release. |
      | http://example.com/distribution1 | http://purl.org/dc/terms/issued                   | 2017-02-11T11:20:00Z                   |
      | http://example.com/distribution1 | http://purl.org/dc/terms/modified                 | 2019-12-02T08:44:00Z                   |
      | http://example.com/distribution1 | http://www.w3.org/ns/dcat#accessURL               | http://www.example.org/release.zip     |
      | http://example.com/distribution1 | http://purl.org/dc/terms/format                   | http://example.com/type1               |
      | http://example.com/distribution1 | http://www.w3.org/ns/adms#status                  | http://purl.org/adms/status/Completed  |
      | http://example.com/distribution1 | http://www.w3.org/ns/adms#representationTechnique | http://example.com/tech1               |
      | http://example.com/distribution1 | http://purl.org/dc/terms/license                  | http://example.com/licence1            |
      | http://example.com/distribution1 | http://www.w3.org/ns/dcat#accessURL               | http://www.example.org/release.zip     |

    # Test that deleting a distribution, removes both the entity and
    # its references.
    Given I delete the "SyncingDistribution.rdf" distribution
    Then there should not be any triples with the subject "http://example.com/distribution2" in the "http://data" graph
    And there should not be any triples with the object "http://example.com/distribution2" in the "http://data" graph
    # The reference to the distribution 2 is not there anymore.
    And the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                                                     |
      | http://example.com/release1 | http://purl.org/dc/terms/title                  | Syncing release - revision                                 |
      | http://example.com/release1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset                          |
      | http://example.com/release1 | http://www.w3.org/2002/07/owl#versionInfo       | 3.0.0                                                      |
      | http://example.com/release1 | http://purl.org/dc/terms/description            | <p>content</p>                                             |
      | http://example.com/release1 | http://purl.org/dc/terms/issued                 | 2017-11-11T11:11:00Z                                       |
      | http://example.com/release1 | http://purl.org/dc/terms/modified               | 2019-12-02T08:44:00Z                                       |
      | http://example.com/release1 | http://www.w3.org/ns/adms#status                | http://purl.org/adms/status/Completed                      |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#keyword               | food                                                       |
      | http://example.com/release1 | http://purl.org/dc/terms/isVersionOf            | http://example.com/solution1                               |
      | http://example.com/release1 | http://www.w3.org/ns/adms#versionNotes          | New 3.0                                                    |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#distribution          | http://example.com/distribution1                           |
      # Inherited from solution.
      | http://example.com/release1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1                                  |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1                                |
      | http://example.com/release1 | http://purl.org/dc/terms/type                   | http://data.europa.eu/dr8/NetworkingInfrastructureEnablers |
      | http://example.com/release1 | http://purl.org/dc/terms/language               | http://example.com/language1                               |
      | http://example.com/release1 | http://purl.org/dc/terms/spatial                | http://example.com/country1                                |

    # Delete the distribution 3 to also test the link from solutions.
    Given I delete the "SyncingSolution.zip 3" distribution
    Then there should not be any triples with the subject "http://example.com/distribution3" in the "http://data" graph
    And there should not be any triples with the object "http://example.com/distribution3" in the "http://data" graph
    # The reference to the distribution 3 is not there anymore.
    And the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                                     |
      | http://example.com/solution1 | http://purl.org/dc/terms/title                  | Syncing solution updated                                   |
      | http://example.com/solution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset                          |
      | http://example.com/solution1 | http://purl.org/dc/terms/description            | <p>content</p>                                             |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#keyword               | ADMS                                                       |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#keyword               | validation                                                 |
      | http://example.com/solution1 | http://purl.org/dc/terms/language               | http://example.com/language1                               |
      | http://example.com/solution1 | http://purl.org/dc/terms/hasVersion             | http://example.com/release1                                |
      | http://example.com/solution1 | http://purl.org/dc/terms/issued                 | 2017-11-01T08:00:00Z                                       |
      | http://example.com/solution1 | http://purl.org/dc/terms/modified               | 2017-12-01T08:43:00Z                                       |
      | http://example.com/solution1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1                                  |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1                                |
      | http://example.com/solution1 | http://purl.org/dc/terms/type                   | http://data.europa.eu/dr8/NetworkingInfrastructureEnablers |
      | http://example.com/solution1 | http://purl.org/dc/terms/spatial                | http://example.com/country1                                |

    # Test that deleting a release, removes both the entity and
    # its references.
    Given I delete the "Syncing release - revision" release
    Then there should not be any triples with the subject "http://example.com/release1" in the "http://data" graph
    And there should not be any triples with the object "http://example.com/release1" in the "http://data" graph
    # The reference to the release 1 is not there anymore.
    And the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                                     |
      | http://example.com/solution1 | http://purl.org/dc/terms/title                  | Syncing solution updated                                   |
      | http://example.com/solution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset                          |
      | http://example.com/solution1 | http://purl.org/dc/terms/description            | <p>content</p>                                             |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#keyword               | ADMS                                                       |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#keyword               | validation                                                 |
      | http://example.com/solution1 | http://purl.org/dc/terms/language               | http://example.com/language1                               |
      | http://example.com/solution1 | http://purl.org/dc/terms/issued                 | 2017-11-01T08:00:00Z                                       |
      | http://example.com/solution1 | http://purl.org/dc/terms/modified               | 2017-12-01T08:43:00Z                                       |
      | http://example.com/solution1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1                                  |
      | http://example.com/solution1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1                                |
      | http://example.com/solution1 | http://purl.org/dc/terms/type                   | http://data.europa.eu/dr8/NetworkingInfrastructureEnablers |
      | http://example.com/solution1 | http://purl.org/dc/terms/spatial                | http://example.com/country1                                |

    # Test that deleting a solution, removes both the entity and
    # its references.
    Given I delete the "Syncing solution updated" solution
    Then there should not be any triples with the subject "http://example.com/solution1" in the "http://data" graph
    And there should not be any triples with the object "http://example.com/solution1" in the "http://data" graph
    # The reference to the solution 1 is not there anymore.
    And the following "http://data" graph triples exist in the RDF backend:
      | subject                        | predicate                                       | object                            |
      | http://example.com/collection1 | http://purl.org/dc/terms/title                  | Syncing collection updated        |
      | http://example.com/collection1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Catalog |
      | http://example.com/collection1 | http://purl.org/dc/terms/description            | Description of the collection.    |
      | http://example.com/collection1 | http://www.w3.org/ns/dcat#contactPoint          | http://example.com/contact1       |
      | http://example.com/collection1 | http://purl.org/dc/terms/publisher              | http://example.com/owner1         |
      | http://example.com/collection1 | http://purl.org/dc/terms/issued                 | 2016-07-13T13:00:00Z              |
      | http://example.com/collection1 | http://purl.org/dc/terms/modified               | __changed__                       |
      | http://example.com/collection1 | http://xmlns.com/foaf/0.1/homepage              | http://www.example.com/msm/       |
      | http://example.com/collection1 | http://purl.org/dc/terms/spatial                | http://example.com/country1       |

    # Test that deleting a collection, removes the entity.
    Given I delete the "Syncing collection updated" collection
    Then there should not be any triples with the subject "http://example.com/collection1" in the "http://data" graph
    And there should not be any triples with the object "http://example.com/collection1" in the "http://data" graph

  Scenario: RDF entities for EIF perspective are correctly created.
    Given solution content:
      | title        | description                                      | state     | uri                          |
      | EIF solution | Some description not to be shown in the EIF page | published | http://example.com/solution1 |
    And the following EIF perspective data for the "EIF solution" solution:
      | Description    | This should be in the description field.                                                       |
      | Principle      | Underlying Principle 1: Subsidiarity and proportionality, Underlying Principle 3: Transparency |
      | Layer          | Interoperability Layer 3: Legal interoperability                                               |
      | uri            | http://example.com/eif-perspective1                                                            |
      | Recommendation | name: Recommendation 21, description: This is recommended 1                                    |
      | Recommendation | name: Recommendation 15, description: This is recommended 2                                    |
    And the following EIF perspective data for the "EIF solution" solution:
      | Description    | Description field of the second item.                       |
      | Principle      | Underlying Principle 3: Transparency                        |
      | Layer          | Interoperability Layer 1: Interoperability governance       |
      | Model          | Basic Component 4: Open data                                |
      | uri            | http://example.com/eif-perspective2                         |
      | Recommendation | name: Recommendation 21, description: This is recommended 3 |
      | Recommendation | name: Recommendation 15, description: This is recommended 4 |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                             | predicate                                       | object                                        |
      | http://example.com/eif-perspective1 | http://purl.org/dc/terms/description            | This should be in the description field.      |
      | http://example.com/eif-perspective1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/eif/adms/PerspectiveEIF |
      | http://example.com/eif-perspective1 | http://www.w3.org/ns/adms#aboutAsset            | http://example.com/solution1                  |
      | http://example.com/eif-perspective1 | http://data.europa.eu/eif/Layer                 | http://data.europa.eu/IL3                     |
      | http://example.com/eif-perspective1 | http://data.europa.eu/eif/Principle             | http://data.europa.eu/UP1                     |
      | http://example.com/eif-perspective1 | http://data.europa.eu/eif/Principle             | http://data.europa.eu/UP3                     |
      | http://example.com/eif-perspective2 | http://purl.org/dc/terms/description            | Description field of the second item.         |
      | http://example.com/eif-perspective2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/eif/adms/PerspectiveEIF |
      | http://example.com/eif-perspective2 | http://www.w3.org/ns/adms#aboutAsset            | http://example.com/solution1                  |
      | http://example.com/eif-perspective2 | http://data.europa.eu/eif/Layer                 | http://data.europa.eu/IL1                     |
      | http://example.com/eif-perspective2 | http://data.europa.eu/eif/Principle             | http://data.europa.eu/UP3                     |
      | http://example.com/eif-perspective2 | http://data.europa.eu/eif/Model                 | http://data.europa.eu/BC4                     |
      | http://example.com/eif-perspective1 | http://data.europa.eu/eif/Recommendation        | bnode:1                                       |
      | http://example.com/eif-perspective1 | http://data.europa.eu/eif/Recommendation        | bnode:2                                       |
      | http://example.com/eif-perspective2 | http://data.europa.eu/eif/Recommendation        | bnode:3                                       |
      | http://example.com/eif-perspective2 | http://data.europa.eu/eif/Recommendation        | bnode:4                                       |
      | bnode:1                             | http://purl.org/dc/terms/description            | This is recommended 1                         |
      | bnode:1                             | http://data.europa.eu/eif/adms/qualifier        | http://data.europa.eu/Rec21                             |
      | bnode:2                             | http://purl.org/dc/terms/description            | This is recommended 2                         |
      | bnode:2                             | http://data.europa.eu/eif/adms/qualifier        | http://data.europa.eu/Rec15                             |
      | bnode:3                             | http://purl.org/dc/terms/description            | This is recommended 3                         |
      | bnode:3                             | http://data.europa.eu/eif/adms/qualifier        | http://data.europa.eu/Rec21                    |
      | bnode:4                             | http://purl.org/dc/terms/description            | This is recommended 4                         |
      | bnode:4                             | http://data.europa.eu/eif/adms/qualifier        | http://data.europa.eu/Rec15                   |
    When I update the entity having "http://example.com/eif-perspective1" as RDF resource:
      | eif_description | This should be in the new description field. |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                             | predicate                                       | object                                              |
      | http://example.com/eif-perspective1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://data.europa.eu/eif/adms/PerspectiveEIF       |
      | http://example.com/eif-perspective1 | http://purl.org/dc/terms/description            | <p>This should be in the new description field.</p> |
      | http://example.com/eif-perspective1 | http://www.w3.org/ns/adms#aboutAsset            | http://example.com/solution1                        |
      | http://example.com/eif-perspective1 | http://data.europa.eu/eif/Layer                 | http://data.europa.eu/IL3                           |
      | http://example.com/eif-perspective1 | http://data.europa.eu/eif/Principle             | http://data.europa.eu/UP1                           |
      | http://example.com/eif-perspective1 | http://data.europa.eu/eif/Principle             | http://data.europa.eu/UP3                           |

  Scenario: RDF entities for Contact information are correctly created.
    Given contact_information content:
      | uri                         | name            | email                    | website url            |
      | http://example.com/contact1 | Syncing contact | jocelyn.bass@example.com | http://www.example.org |
      | http://example.com/contact2 | Geoffrey Bryce  | geoffrey.bryce@exam.com  |                        |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                               |
      | http://example.com/contact1 | http://www.w3.org/2006/vcard/ns#fn              | Syncing contact                      |
      | http://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasEmail        | jocelyn.bass@example.com             |
      | http://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasURL          | http://www.example.org               |
      | http://example.com/contact1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/2006/vcard/ns#Kind |
      | http://example.com/contact2 | http://www.w3.org/2006/vcard/ns#fn              | Geoffrey Bryce                       |
      | http://example.com/contact2 | http://www.w3.org/2006/vcard/ns#hasEmail        | geoffrey.bryce@exam.com              |
      | http://example.com/contact2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/2006/vcard/ns#Kind |
    When I update the entity having "http://example.com/contact1" as RDF resource:
      | title | Syncing contact Bryce          |
      | email | jocelyn.bass.bryce@example.com |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                               |
      | http://example.com/contact1 | http://www.w3.org/2006/vcard/ns#fn              | Syncing contact Bryce                |
      | http://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasEmail        | jocelyn.bass.bryce@example.com       |
      | http://example.com/contact1 | http://www.w3.org/2006/vcard/ns#hasURL          | http://www.example.org               |
      | http://example.com/contact1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/2006/vcard/ns#Kind |

  Scenario: RDF entities for Distribution are correctly created.
    Given solution content:
      | title         | state     |
      | Test solution | published |
    And release content:
      | title        | is version of | state     |
      | Test release | Test solution | published |
    And distribution content:
      | uri                              | title          | description                  | release      | access url                      | created              | publication date     | changed              | state     |
      | http://example.com/distribution1 | Some Distro    | Übermensch foot size         | Test release | http://example.com/some-distro1 | 2024-07-31T10:19:33Z | 2024-07-31T10:19:33Z | 2024-07-31T10:19:33Z | published |
      | http://example.com/distribution2 | Another distro | Description for other distro | Test release | http://example.com/some-distro2 | 2024-08-01T10:19:33Z | 2024-08-01T10:19:33Z | 2024-08-01T10:19:33Z | published |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                          | predicate                                       | object                                 |
      | http://example.com/distribution1 | http://purl.org/dc/terms/title                  | Some Distro                            |
      | http://example.com/distribution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Distribution |
      | http://example.com/distribution1 | http://purl.org/dc/terms/description            | Übermensch foot size                   |
      | http://example.com/distribution1 | http://www.w3.org/ns/dcat#accessURL             | http://example.com/some-distro1        |
      | http://example.com/distribution1 | http://purl.org/dc/terms/issued                 | 2024-07-31T10:19:33Z                   |
      | http://example.com/distribution1 | http://purl.org/dc/terms/modified               | 2024-07-31T10:19:33Z                   |
      | http://example.com/distribution2 | http://purl.org/dc/terms/title                  | Another distro                         |
      | http://example.com/distribution2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Distribution |
      | http://example.com/distribution2 | http://purl.org/dc/terms/description            | Description for other distro           |
      | http://example.com/distribution2 | http://www.w3.org/ns/dcat#accessURL             | http://example.com/some-distro2        |
      | http://example.com/distribution2 | http://purl.org/dc/terms/issued                 | 2024-08-01T10:19:33Z                   |
      | http://example.com/distribution2 | http://purl.org/dc/terms/modified               | 2024-08-01T10:19:33Z                   |
    When I update the entity having "http://example.com/distribution1" as RDF resource:
      | title       | Some Distro new title       |
      | description | Some Distro new description |
      | changed     | 2024-07-31T15:20:00Z        |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                          | predicate                                       | object                                 |
      | http://example.com/distribution1 | http://purl.org/dc/terms/title                  | Some Distro new title                  |
      | http://example.com/distribution1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Distribution |
      | http://example.com/distribution1 | http://purl.org/dc/terms/description            | <p>Some Distro new description</p>     |
      | http://example.com/distribution1 | http://www.w3.org/ns/dcat#accessURL             | http://example.com/some-distro1        |
      | http://example.com/distribution1 | http://purl.org/dc/terms/issued                 | 2024-07-31T10:19:33Z                   |
      | http://example.com/distribution1 | http://purl.org/dc/terms/modified               | 2024-07-31T15:20:00Z                   |

  Scenario: RDF entities for Licences are correctly created.
    Given licence content:
      | uri                         | title              | description    | licence type  | deprecated |
      | http://example.com/license1 | Some license       |                | Public domain | no         |
      | http://example.com/license2 | Some other license | A new licence. | Public domain | yes        |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                                   |
      | http://example.com/license1 | http://www.w3.org/2000/01/rdf-schema#label      | Some license                             |
      | http://example.com/license1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/dc/terms/LicenseDocument |
      | http://example.com/license1 | http://purl.org/dc/terms/type                   | http://purl.org/adms/licence/PD          |
      | http://example.com/license1 | http://joinup.eu/licence/deprecated             | 0                                        |
      | http://example.com/license2 | http://www.w3.org/2000/01/rdf-schema#label      | Some other license                       |
      | http://example.com/license2 | http://purl.org/dc/terms/description            | A new licence.                           |
      | http://example.com/license2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/dc/terms/LicenseDocument |
      | http://example.com/license2 | http://purl.org/dc/terms/type                   | http://purl.org/adms/licence/PD          |
      | http://example.com/license2 | http://joinup.eu/licence/deprecated             | 1                                        |
    When I update the entity having "http://example.com/license2" as RDF resource:
      | description | A new updated licence. |
      | deprecated  | 0                      |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                                   |
      | http://example.com/license2 | http://www.w3.org/2000/01/rdf-schema#label      | Some other license                       |
      | http://example.com/license2 | http://purl.org/dc/terms/description            | <p>A new updated licence.</p>            |
      | http://example.com/license2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://purl.org/dc/terms/LicenseDocument |
      | http://example.com/license2 | http://purl.org/dc/terms/type                   | http://purl.org/adms/licence/PD          |
      | http://example.com/license2 | http://joinup.eu/licence/deprecated             | 0                                        |

  Scenario: RDF entities for Owners and Owner types are correctly created.
    Given owner_type terms:
      | uri                          | name       |
      | http://joinup.eu/owner-type1 | University |
    And owner content:
      | uri                       | name          | state     | owner type |
      | http://example.com/owner1 | Syncing owner | published | University |
      | http://example.com/owner2 | Johnny Guitar | published | University |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                      | predicate                                       | object                                      |
      | http://example.com/owner1    | http://xmlns.com/foaf/0.1/name                  | Syncing owner                               |
      | http://example.com/owner1    | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://xmlns.com/foaf/0.1/Agent             |
      | http://example.com/owner1    | http://purl.org/dc/terms/type                   | http://joinup.eu/owner-type1                |
      | http://example.com/owner2    | http://xmlns.com/foaf/0.1/name                  | Johnny Guitar                               |
      | http://example.com/owner2    | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://xmlns.com/foaf/0.1/Agent             |
      | http://example.com/owner2    | http://purl.org/dc/terms/type                   | http://joinup.eu/owner-type1                |
      | http://joinup.eu/owner-type1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/2004/02/skos/core#Concept |
      | http://joinup.eu/owner-type1 | http://www.w3.org/2004/02/skos/core#inScheme    | http://purl.org/adms/publishertype/1.0      |
      | http://joinup.eu/owner-type1 | http://www.w3.org/2004/02/skos/core#prefLabel   | University                                  |
    When I update the entity having "http://example.com/owner1" as RDF resource:
      | title | Stephen Bass |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                   | predicate                                       | object                          |
      | http://example.com/owner1 | http://xmlns.com/foaf/0.1/name                  | Stephen Bass                    |
      | http://example.com/owner1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://xmlns.com/foaf/0.1/Agent |
      | http://example.com/owner1 | http://purl.org/dc/terms/type                   | http://joinup.eu/owner-type1    |

  Scenario: RDF entities for Releases are correctly created.
    Given solution content:
      | title       | state     | uri                          |
      | Early Omega | published | http://example.com/solution1 |
    And release content:
      | uri                         | title    | documentation | release number | release notes | published_at         | changed              | is version of | state     | status    | keywords   |
      | http://example.com/release1 | Omega3   | text.pdf      | 3.0.0          | New 3.0       | 2024-07-31T10:19:33Z | 2024-07-31T10:19:33Z | Early Omega   | published | Completed | food       |
      | http://example.com/release2 | Omega3.1 | text.pdf      | 3.1.0          | New 3.1       | 2024-08-01T10:19:33Z | 2024-08-01T10:19:33Z | Early Omega   | published | Completed | food,games |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                                |
      | http://example.com/release1 | http://purl.org/dc/terms/title                  | Omega3                                |
      | http://example.com/release1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset     |
      | http://example.com/release1 | http://www.w3.org/2002/07/owl#versionInfo       | 3.0.0                                 |
      | http://example.com/release1 | http://purl.org/dc/terms/issued                 | 2024-07-31T10:19:33Z                  |
      | http://example.com/release1 | http://purl.org/dc/terms/modified               | 2024-07-31T10:19:33Z                  |
      | http://example.com/release1 | http://www.w3.org/ns/adms#status                | http://purl.org/adms/status/Completed |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#keyword               | food                                  |
      | http://example.com/release1 | http://purl.org/dc/terms/isVersionOf            | http://example.com/solution1          |
      | http://example.com/release1 | http://www.w3.org/ns/adms#versionNotes          | New 3.0                               |
      | http://example.com/release2 | http://purl.org/dc/terms/title                  | Omega3.1                              |
      | http://example.com/release2 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset     |
      | http://example.com/release2 | http://www.w3.org/2002/07/owl#versionInfo       | 3.1.0                                 |
      | http://example.com/release2 | http://purl.org/dc/terms/issued                 | 2024-08-01T10:19:33Z                  |
      | http://example.com/release2 | http://purl.org/dc/terms/modified               | 2024-08-01T10:19:33Z                  |
      | http://example.com/release2 | http://www.w3.org/ns/adms#status                | http://purl.org/adms/status/Completed |
      | http://example.com/release2 | http://www.w3.org/ns/dcat#keyword               | food                                  |
      | http://example.com/release2 | http://www.w3.org/ns/dcat#keyword               | games                                 |
      | http://example.com/release2 | http://purl.org/dc/terms/isVersionOf            | http://example.com/solution1          |
      | http://example.com/release2 | http://www.w3.org/ns/adms#versionNotes          | New 3.1                               |
    When I update the entity having "http://example.com/release1" as RDF resource:
      | title          | Omega the third      |
      | field_keywords | food,drinks          |
      | changed        | 2024-07-31T15:20:00Z |
    Then the following "http://data" graph triples exist in the RDF backend:
      | subject                     | predicate                                       | object                                |
      | http://example.com/release1 | http://purl.org/dc/terms/title                  | Omega the third                       |
      | http://example.com/release1 | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/ns/dcat#Dataset     |
      | http://example.com/release1 | http://www.w3.org/2002/07/owl#versionInfo       | 3.0.0                                 |
      | http://example.com/release1 | http://purl.org/dc/terms/issued                 | 2024-07-31T10:19:33Z                  |
      | http://example.com/release1 | http://purl.org/dc/terms/modified               | 2024-07-31T15:20:00Z                  |
      | http://example.com/release1 | http://www.w3.org/ns/adms#status                | http://purl.org/adms/status/Completed |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#keyword               | food                                  |
      | http://example.com/release1 | http://www.w3.org/ns/dcat#keyword               | drinks                                |
      | http://example.com/release1 | http://purl.org/dc/terms/isVersionOf            | http://example.com/solution1          |
      | http://example.com/release1 | http://www.w3.org/ns/adms#versionNotes          | New 3.0                               |
