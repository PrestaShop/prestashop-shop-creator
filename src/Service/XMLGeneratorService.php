<?php

namespace ShopGenerator\Service;

use Doctrine\Common\Inflector\Inflector;
use RuntimeException;
use ShopGenerator\Generator\EntityGenerator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class XMLGeneratorService
{
    /**
     * @param $configuration
     *
     * @throws RuntimeException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \Symfony\Component\Yaml\Exception\ParseException
     */
    public static function createXML($configuration)
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__.'/../Model');
        $relations = self::initializeDefaultData();
        $relationList = [];

        $fileList = self::sortModelWithDependencies($finder);

        foreach ($fileList as $modelName) {
            $configKey = Inflector::tableize(Inflector::pluralize($modelName));
            $entityModel = Yaml::parse(file_get_contents(__DIR__.'/../Model/'.$modelName.'.yml'));
            if (!array_key_exists($configKey, $configuration)) {
                if (!array_key_exists('primary', $entityModel['fields'])) {
                    throw new RuntimeException('Missing configuration entry for key ' . $configKey);
                } else {
                    $configuration[$configKey] = 1;
                }
            }

            $entity = new EntityGenerator(
                Inflector::tableize($modelName),
                $entityModel,
                $configuration[$configKey],
                $relations,
                $relationList,
                $configuration['langs']
            );
            $entity->create();
            $entity->save();
            $relations = $entity->getRelations();
            $relationList = $entity->getRelationList();

            unset($entity);
            gc_collect_cycles();
        }
    }

    /**
     * Sort the file list depending on the dependencies
     *
     * @param Finder $finder
     *
     * @return array
     */
    private static function sortModelWithDependencies(Finder $finder)
    {
        foreach ($finder as $file) {
            $pathName = $file->getPathname();
            $modelType = str_replace('.yml', '', $file->getFilename());
            $configKey = Inflector::tableize(Inflector::pluralize($modelType));
            $yamlContent = file_get_contents($pathName);
            if (preg_match_all('/relation:\W*(.+)$/uim', $yamlContent, $matches)) {
                foreach ($matches[1] as $dependency) {
                    if ($dependency != $modelType) {
                        $dependencies[$dependency][] = $modelType;
                    }
                }
            }
            $entities[] = $modelType;
        }

        do {
            $current = (isset($sortEntities)) ? $sortEntities : array();
            $sortEntities = array();
            foreach ($entities as $key => $entity) {
                if (isset($dependencies[$entity])) {
                    $min = count($entities) - 1;
                    foreach ($dependencies[$entity] as $item) {
                        if (($key = array_search($item, $sortEntities)) !== false) {
                            $min = min($min, $key);
                        }
                    }
                    if ($min == 0) {
                        array_unshift($sortEntities, $entity);
                    } else {
                        array_splice($sortEntities, $min, 0, array($entity));
                    }
                } else {
                    $sortEntities[] = $entity;
                }
            }
            $entities = $sortEntities;
        } while ($current != $sortEntities);

        return $sortEntities;
    }

    /**
     * Fill the relations table with values from default xml data
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    private static function initializeDefaultData()
    {
        $relations = array();
        $finder = new Finder();
        $finder->files()->in(__DIR__.'/../../default_data');
        foreach ($finder as $file) {
            $entityName = str_replace('.xml', '', $file->getFilename());
            $xml = new \SimpleXMLElement(file_get_contents($file->getPathname()));
            foreach ($xml->entities->$entityName as $values) {
                $relations[$entityName][] = $values;
            }
        }

        return $relations;
    }
}
