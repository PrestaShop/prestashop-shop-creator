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
     */
    public static function createXML($configuration)
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__.'/../Model');
        $relations = self::initializeDefaultData();
        $relationList = [];

        list($dependencies, $fileList) = self::sortModelWithDependencies($finder);

        foreach ($fileList as $modelName) {
            $configKey = Inflector::tableize(Inflector::pluralize($modelName));

            $entityXml = self::generateXML(
                $configKey,
                $modelName,
                $dependencies,
                $relations,
                $relationList,
                $configuration
            );

            $relations = $entityXml->getRelations();
            $relationList = $entityXml->getRelationList();
            // cleanup the relations array as soon as possible to avoid eating too much memory
            self::removeGeneratedDependencies(Inflector::tableize($modelName), $relations, $dependencies);

            unset($entityXml);
            gc_collect_cycles();
        }
    }

    /**
     * @param $entityToRemove
     * @param $relations
     * @param $dependencies
     */
    private static function removeGeneratedDependencies($entityToRemove, &$relations, &$dependencies)
    {
        foreach($dependencies as $key => &$values) {
            foreach($values as $valueKey => $value) {
                if ($value == $entityToRemove) {
                    unset($values[$valueKey]);
                }
                // if there's no more dependencies, we can cleanup the relation list
                if (count($values) == 0) {
                    unset($relations[$key]);
                    unset($dependencies[$key]);
                }
            }
        }
    }

    /**
     * @param string $configKey
     * @param string $modelName
     * @param array  $dependencies
     * @param array  $relations
     * @param array  $relationList
     * @param array  $configuration
     *
     * @return EntityGenerator
     * @throws RuntimeException
     * @throws \Symfony\Component\Yaml\Exception\ParseException
     */
    private static function generateXML(
        $configKey,
        $modelName,
        $dependencies,
        &$relations,
        &$relationList,
        $configuration
    ) {
        $entityModel = Yaml::parse(file_get_contents(__DIR__.'/../Model/'.$modelName.'.yml'));
        if (!array_key_exists($configKey, $configuration)) {
            if (!array_key_exists('primary', $entityModel['fields'])) {
                throw new RuntimeException('Missing configuration entry for key ' . $configKey);
            } else {
                $entityCount = 1;
            }
        } else {
            $entityCount = $configuration[$configKey];
        }

        $entityXml = new EntityGenerator(
            Inflector::tableize($modelName),
            $entityModel,
            $entityCount,
            $dependencies,
            $relations,
            $relationList,
            $configuration['langs']
        );
        $entityXml->create();
        $entityXml->save();

        return $entityXml;
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
        $dependencies = $storedDependencies = $parentEntities = [];

        foreach ($finder as $file) {
            $pathName = $file->getPathname();
            $modelType = str_replace('.yml', '', $file->getFilename());
            $yamlContent = file_get_contents($pathName);
            if (preg_match_all('/relation:\W*(.+)$/uim', $yamlContent, $matches)) {
                foreach ($matches[1] as $dependency) {
                    if ($dependency != $modelType) {
                        $dependencies[$dependency][] = $modelType;
                        $storedDependencies[Inflector::tableize($dependency)][] = Inflector::tableize($modelType);
                    }
                }
            }
            $parentEntities[] = $modelType;
        }

        do {
            $current = (isset($sortEntities)) ? $sortEntities : array();
            $sortEntities = array();
            foreach ($parentEntities as $key => $entity) {
                if (isset($dependencies[$entity])) {
                    $min = count($parentEntities) - 1;
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
            $parentEntities = $sortEntities;
        } while ($current != $sortEntities);

        return [$storedDependencies, $sortEntities];
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
                $relations[$entityName][(string)$values['id']] = $values;
            }
        }

        return $relations;
    }
}
