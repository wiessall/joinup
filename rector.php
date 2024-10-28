<?php

/**
 * @file
 * Rector config file.
 */

declare(strict_types=1);

use DrupalFinder\DrupalFinderComposerRuntime;
use DrupalRector\Set\Drupal10SetList;
use DrupalRector\Set\Drupal8SetList;
use DrupalRector\Set\Drupal9SetList;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
  // Adjust the set lists to be more granular to your Drupal requirements.
  // @todo find out how to only load the relevant rector rules.
  //   Should we try and load \Drupal::VERSION and check?
  $rectorConfig->sets([
    Drupal8SetList::DRUPAL_8,
    Drupal9SetList::DRUPAL_9,
    Drupal10SetList::DRUPAL_10,
  ]);

  $drupalFinder = new DrupalFinderComposerRuntime();
  $drupalRoot = $drupalFinder->getDrupalRoot();
  $rectorConfig->autoloadPaths([
    $drupalRoot . '/core',
    $drupalRoot . '/modules',
    $drupalRoot . '/profiles',
    $drupalRoot . '/themes',
  ]);

  $rectorConfig->skip(['*/upgrade_status/tests/modules/*']);
  $rectorConfig->fileExtensions(['php', 'module', 'theme', 'install', 'profile', 'inc', 'engine']);
  $rectorConfig->importNames(TRUE, FALSE);
  $rectorConfig->importShortClasses(FALSE);

  // Custom Interoperable Europe Portal config.
  $rectorConfig->paths([
    __DIR__ . '/src',
    __DIR__ . '/tests/src',
    $drupalRoot . '/modules/custom',
    $drupalRoot . '/profiles/joinup',
    $drupalRoot . '/themes/iop',
    $drupalRoot . '/themes/ventuno',
  ]);

  $rectorConfig->skip([
    // @todo Can we fix this?
    $drupalRoot . '/modules/custom/custom_page/src/ProxyClass/CustomPageOgMenuLinksManager.php',
  ]);
};
