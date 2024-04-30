@api
Feature: Joinup should be ADMS-AP compliant.

  Scenario: Validate the entities in the published graph.
    Given users:
      | Username         | E-mail                       |
      | Andre Munson     | andre.munson@example.com     |
      | Branson Winthrop | branson.winthrop@example.com |
    And contacts:
      | name           | email                    | website url            |
      | Jocelyn Bass   | jocelyn.bass@example.com | http://www.example.org |
      | Geoffrey Bryce | geoffrey.bryce@exam.com  |                        |
    And owner:
      | name       | type    |
      | Teddy Bass | Company |
    And licence content:
      | title       | description       | licence type  |
      | Foo licence | Some nice licence | Public domain |
    And the following collection:
      | title               | Morbid Scattered Microphone    |
      | author              | Andre Munson                   |
      | abstract            | Abstract.                      |
      | description         | Description of the collection. |
      | access url          | http://www.example.com/msm/    |
      | logo                | logo.png                       |
      | contact information | Jocelyn Bass                   |
      | owner               | Teddy Bass                     |
      | creation date       | 2016-07-13T13:00               |
      | modification date   | 2017-06-30T11:27               |
      | closed              | no                             |
      | content creation    | facilitators and authors       |
      | moderation          | no                             |
      | topic               | Demography                     |
      | spatial coverage    | Belgium                        |
      | state               | published                      |
      | featured            | no                             |
    And the following solutions:
      | title        | collection                  | author           | description    | logo     | owner      | contact information | creation date   | modification date | documentation | content creation | keywords         | landing page                    | language | metrics page                    | moderation | topic                    | related solutions | solution type                      | source code repository        | spatial coverage | status    | translation | webdav creation | webdav url                    | wiki                        | state     | featured | pinned in |
      | Early Omega  | Morbid Scattered Microphone | Andre Munson     | <p>content</p> | logo.png | Teddy Bass | Jocelyn Bass        | 2017-11-01T8:00 | 2017-12-01T8:43   | text.pdf      | registered users | ADMS, validation | http://www.example.com/landing1 | Flemish  | http://www.example.org/metrics1 | no         | EU and European Policies |                   | Networking Infrastructure Enablers | http://www.example.org/source | Belgium          | Completed |             | no              | http://www.example.org/webdav | http://www.example.org/wiki | published | no       |           |
      | Snake Timely | Morbid Scattered Microphone | Branson Winthrop | <p>content</p> | logo.png | Teddy Bass | Jocelyn Bass        | 2015-03-03T8:00 | 2018-02-14T18:43  | text.pdf      | registered users |                  | http://www.example.com/landing2 | English  | http://www.example.org/metrics2 | yes        | Demography               | Early Omega       | Networking Infrastructure Enablers | http://www.example.org/source | Belgium          | Completed |             | yes             | http://www.example.org/webdav | http://www.example.org/wiki | published | no       |           |
    And the following releases:
      | title  | documentation | release number | release notes | creation date    | is version of | state     | status    | spatial coverage | keywords | language |
      | Omega3 | text.pdf      | 3.0.0          | New 3.0       | 2017-11-11T11:11 | Early Omega   | published | Completed | Belgium          | food     | English  |
    And the following distributions:
      | title      | description                    | creation date    | access url                        | parent | downloads | licence     | format | status    | representation technique | state     |
      | Omega3.zip | The zipped version of Omega 3. | 2017-11-11T11:20 | http://www.example.org/omega3.zip | Omega3 | 232       | Foo licence | ZIP    | Completed | Datalog                  | published |
    Then the ADMS-AP data of the published entities in Joinup is valid
