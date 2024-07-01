<?php

$finder = new TwigCsFixer\File\Finder();
$finder->exclude([
  'dist',
  'tmp',
  'web/core',
  'web/modules/contrib',
  'web/themes/contrib'
]);

$config = new TwigCsFixer\Config\Config();
$config->setFinder($finder);
$config->addTokenParser(new Drupal\Core\Template\TwigTransTokenParser());

return $config;
