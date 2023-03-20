<?php

namespace ShopGenerator\Service;

use ShopGenerator\Command\GenerateFixturesCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\ErrorHandler\Debug;

date_default_timezone_set('UTC');

// include the composer AutoLoader
require_once __DIR__ . '/../vendor/autoload.php';

Debug::enable();


$command = new GenerateFixturesCommand();

$app = new Application();
$app->add($command);
$app->run();
