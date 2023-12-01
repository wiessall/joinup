#!/usr/bin/env php
<?php

$patches = array_merge(
  ...array_values(
    json_decode(file_get_contents("composer.json"), TRUE)["extra"]["patches"]
  )
);
array_walk($patches, function (string $patch): void {
  if (preg_match('@^https?://@', $patch)) {
    echo "\e[31mPatch $patch should be local\033[0m\n";
    exit(1);
  }
});
