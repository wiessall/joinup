#!/usr/bin/env php
<?php

$has_errors = FALSE;
$patches = array_merge(
  ...array_values(
    json_decode(file_get_contents("composer.json"), TRUE)["extra"]["patches"]
  )
);
array_walk($patches, function (string $patch) use (&$has_errors): void {
  if (preg_match('@^https?://@', $patch)) {
    echo "\e[31mPatch $patch should be local\033[0m\n";
    $has_errors = TRUE;
  }
});

exit($has_errors ? 1 : 0);
