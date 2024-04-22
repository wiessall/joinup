<?php

/** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$mapping = $storage->getTableMapping();
$t = [];
foreach (\Drupal::getContainer()->get('entity_field.manager')->getFieldStorageDefinitions('taxonomy_term') as $fieldName => $definition) {
  print "$fieldName\n";
//  print $mapping->getFieldTableName($fieldName) . "\n";
  print_r($mapping->getAllFieldTableNames($fieldName)); print "\n";
  $t = array_unique([...$t, ...$mapping->getAllFieldTableNames($fieldName)]);
//  print_r($mapping->getAllColumns($mapping->getFieldTableName($fieldName))); print "\n";
  print "\n";

}
//$t = $mapping->getTableNames();
sort($t);
print_r($t);

