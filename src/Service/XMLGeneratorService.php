<?php

namespace ShopGenerator\Service;

use Doctrine\Common\Inflector\Inflector;
use SebastianBergmann\GlobalState\RuntimeException;
use ShopGenerator\Generator\EntityGenerator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class XMLGeneratorService
{
    /**
     * @param array      $configuration
     */
    public static function createXML($configuration)
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__.'/../Model');
        $relations = array();

        foreach ($finder as $file) {
            $pathName = $file->getPathname();
            $modelType = str_replace('.yml', '', $file->getFilename());
            $configKey = strtolower(Inflector::pluralize($modelType));
            if (!array_key_exists($configKey, $configuration)) {
                throw new RuntimeException('Missing configuration entry for key '.$configKey);
            }
            $entityModel = Yaml::parse(file_get_contents($pathName));

            $entity = new EntityGenerator($entityModel, $configuration[$configKey], $relations);
            $entity->create();
            $relations = $entity->getRelations();
        }
    }
}