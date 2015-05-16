<?php

require_once($amissPath.'/../doc/demo/ar.php');

$connector = new \PDOK\Connector('sqlite::memory:');
$manager = Amiss\Factory::createSqlManager($connector, array(
    'cache'=>get_note_cache(),
    'typeHandlers'=>array(),
));

$manager->mapper->objectNamespace = 'Amiss\Demo\Active';
$connector->exec(file_get_contents($amissPath.'/../doc/demo/schema.sqlite.sql'));
$connector->exec(file_get_contents($amissPath.'/../doc/demo/testdata.sql'));

Amiss\Sql\ActiveRecord::setManager($manager);
