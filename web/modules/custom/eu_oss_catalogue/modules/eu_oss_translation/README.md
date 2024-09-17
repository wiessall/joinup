## EU OSS Translation | EU OSS Catalogue

Module provides translations to OSS solution nodes

### Description
When an OSS solution entity is created or updated, the system attempts to create a TMGMT job.

### Configuration
* Configure the TMGMT translator provider:
* It is recommended to use the provider implemented by the "tmgmt_ec_etranslation" module.
* Select "Auto accept finished translations" within the provider if you want to skip the review process.
* Set the translator provider for the eu_oss_translation module (the default is set to ec_etranslation).


### Useful commands

The module ships with a Drush command that allows to create the translation job.
Run the following, to create translation job:
```bash
./vendor/bin/drush eu-oss:generate-translation-job SOLUTION_ID
```

Use the queue system to process translations retrieved from the eTranslation service:
```bash
./vendor/bin/drush queue:run tmgmt_ec_etranslation_translation
```
