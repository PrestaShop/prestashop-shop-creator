<?php
namespace ShopGenerator\Service;

use Symfony\Component\Yaml\Yaml;

date_default_timezone_set('UTC');

// include the composer AutoLoader
require_once __DIR__ . '/../vendor/autoload.php';

$configuration = Yaml::parse(file_get_contents(__DIR__ . '/config/config.yml'));

XMLGeneratorService::createXML($configuration['parameters']);