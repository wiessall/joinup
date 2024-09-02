@api
Feature: Joinup should be ADMS-AP compliant.

  Scenario: Validate the entities in the published graph.
    Given users:
      | Username         | E-mail                       |
      | Andre Munson     | andre.munson@example.com     |
      | Branson Winthrop | branson.winthrop@example.com |
    And contact_information content:
      | name           | email                    | website url            |
      | Jocelyn Bass   | jocelyn.bass@example.com | http://www.example.org |
      | Geoffrey Bryce | geoffrey.bryce@exam.com  |                        |
    And owner content:
      | name       | owner type |
      | Teddy Bass | Company    |
    And licence content:
      | title       | description       | licence type  |
      | Foo licence | Some nice licence | Public domain |
    And collection content:
      | title                       | author       | abstract  | description                    | access url                  | logo     | contact information | owner      | creation date    | modification date | closed | content creation         | moderation | topic      | spatial coverage | state     | featured |
      | Morbid Scattered Microphone | Andre Munson | Abstract. | Description of the collection. | http://www.example.com/msm/ | logo.png | Jocelyn Bass        | Teddy Bass | 2016-07-13T13:00 | 2017-06-30T11:27  | no     | facilitators and authors | no         | Demography | Belgium          | published | no       |
    Given solution content:
      | title        | collection                  | author           | description    | logo     | owner      | contact information | creation date   | modification date | documentation | content creation | keywords         | landing page                    | language | metrics page                    | moderation | topic                    | related solutions | solution type                      | source code repository        | spatial coverage | status    | translation | webdav creation | webdav url                    | wiki                        | state     | featured | pinned in |
      | Early Omega  | Morbid Scattered Microphone | Andre Munson     | <p>content</p> | logo.png | Teddy Bass | Jocelyn Bass        | 2017-11-01T8:00 | 2017-12-01T8:43   | text.pdf      | registered users | ADMS, validation | http://www.example.com/landing1 | Flemish  | http://www.example.org/metrics1 | no         | EU and European Policies |                   | Networking Infrastructure Enablers | http://www.example.org/source | Belgium          | Completed |             | no              | http://www.example.org/webdav | http://www.example.org/wiki | published | no       |           |
      | Snake Timely | Morbid Scattered Microphone | Branson Winthrop | <p>content</p> | logo.png | Teddy Bass | Jocelyn Bass        | 2015-03-03T8:00 | 2018-02-14T18:43  | text.pdf      | registered users |                  | http://www.example.com/landing2 | English  | http://www.example.org/metrics2 | yes        | Demography               | Early Omega       | Networking Infrastructure Enablers | http://www.example.org/source | Belgium          | Completed |             | yes             | http://www.example.org/webdav | http://www.example.org/wiki | published | no       |           |
    And release content:
      | title  | documentation | release number | release notes | creation date    | is version of | state     | status    | spatial coverage | keywords | language |
      | Omega3 | text.pdf      | 3.0.0          | New 3.0       | 2017-11-11T11:11 | Early Omega   | published | Completed | Belgium          | food     | English  |
    # The two cases cover the two possible access URL types extracted.
    # @see \Drupal\joinup_distribution\EventSubscriber\DistributionParentRdfSyncSubscriber::addDistributionParentRelation().
    And distribution content:
      | title      | description                    | creation date    | access url                        | release | downloads | licence     | format | status    | representation technique | state     |
      | Omega3.zip | The zipped version of Omega 3. | 2017-11-11T11:20 | http://www.example.org/omega3.zip | Omega3  | 232       | Foo licence | ZIP    | Completed | Datalog                  | published |
      | Omega3.rdf | The RDF version of Omega 3.    | 2017-11-11T11:20 | empty.rdf                         | Omega3  | 232       | Foo licence | ZIP    | Completed | Datalog                  | published |
    Then the ADMS-AP data of the published entities in Joinup is valid
