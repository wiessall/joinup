<?php

$finder = new TwigCsFixer\File\Finder();
$finder->exclude('web/core');
$finder->exclude('web/modules/contrib');
$finder->exclude('web/themes/contrib');

$config = new TwigCsFixer\Config\Config();
$config->setFinder($finder);

return $config;
