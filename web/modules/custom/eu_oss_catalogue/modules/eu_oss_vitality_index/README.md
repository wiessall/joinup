## Vitality Index Generator | EU OSS Catalogue

This is an EU OSS Catalogue submodule generating Vitality Index feature,
both in terms of calculation and related OSS Solution content field type and
formatter.

*The Vitality Index indicates the degree of activity carried out in the last
time period on each OSS Solution repository indexed in the Catalogue.
It reproduces exactly (in its definition and calculation) that envisaged by the
guidelines on acquisition and reuse of software for Public Administrations
defined by the [Agency for Digital Italy - AGID](https://www.agid.gov.it/it/agenzia),
and currently officially implemented within the
[Developers.Italia OSS Solutions Catalogue](https://developers.italia.it/en/software).*

*This vitality index is calculated on the time window of the last 60 days (and
updated once a week within this catalogue) taking into consideration the
following four categories:*

- ***Code activity**: the number of commits and merges per day,*
- ***Release history**: the number of releases per day,*
- ***User community**: the number of unique authors,*
- ***Longevity**: the age of the project,*

*The ranges of each measurement can be found in the file
[vitality-ranges.yml](https://github.com/italia/publiccode-crawler/blob/main/vitality-ranges.yml).*


### Functional Calculation Summary
The Vitality Index calculation is performed throughout a
Vitality index processor and a Vitality Index Generator Service.

The Vitality Index calculation is going to run for each OSS Solution in the
Catalogue, according to eu_oss_vitality_index_cron job, feeding an
eu_oss_vitality_index worker queue.


