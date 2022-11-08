<?php

//require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

$kirby = new Kirby();

echo $kirby->render();
