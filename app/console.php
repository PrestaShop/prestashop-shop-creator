<?php

namespace ShopGenerator\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

date_default_timezone_set('UTC');

// include the composer AutoLoader
require_once __DIR__ . '/../vendor/autoload.php';

try {
    (new Filesystem())->remove(__DIR__ . '/../generated_data');
    $configuration = Yaml::parse(file_get_contents(__DIR__ . '/config/config.yml'));
    XMLGeneratorService::createXML($configuration['parameters']);
} catch (\Exception $e) {
    echo $e->getMessage();
}
