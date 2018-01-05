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
        $parentEntities = [];

        $fileList = self::sortModelWithDependencies($finder);

        foreach ($fileList as $modelName) {
            $dependencies = [];
            $configKey = Inflector::tableize(Inflector::pluralize($modelName));

            // if the current modelName has been defined by another entity as a dependency, use their value
            // to generate the new entity
            if (array_key_exists($configKey, $parentEntities)) {
                foreach ($parentEntities[$configKey] as $key => $parentEntities) {
                    $entityXml = self::generateXML(
                        $configKey,
                        $modelName,
                        $relations,
                        $relationList,
                        $configuration,
                        [$key => $parentEntities]
                    );

                    $relations = $entityXml->getRelations();
                    $relationList = $entityXml->getRelationList();
                }
            } else {
                $entityXml = self::generateXML(
                    $configKey,
                    $modelName,
                    $relations,
                    $relationList,
                    $configuration
                );

                $relations = $entityXml->getRelations();
                $relationList = $entityXml->getRelationList();
            }

            if (array_key_exists($configKey, $configuration) && is_array($configuration[$configKey])) {
                $dependencies = $configuration[$configKey]['dependencies'];
            }

            if (!empty($dependencies)) {
                foreach ($dependencies as $dependency) {
                    $parentEntities[$dependency][Inflector::tableize($modelName)] = $entityXml->getEntities();
                }
            }

            unset($entityXml);
            gc_collect_cycles();
        }
    }

    /**
     * @param string $configKey
     * @param string $modelName
     * @param array  $relations
     * @param array  $relationList
     * @param array  $configuration
     * @param \SimpleXMLElement $parentEntities
     *
     * @return EntityGenerator
     * @throws RuntimeException
     * @throws \Symfony\Component\Yaml\Exception\ParseException
     */
    private static function generateXML(
        $configKey,
        $modelName,
        $relations,
        $relationList,
        $configuration,
        $parentEntities = null
    ) {
        $entityModel = Yaml::parse(file_get_contents(__DIR__.'/../Model/'.$modelName.'.yml'));
        if (!array_key_exists($configKey, $configuration)) {
            if (!array_key_exists('primary', $entityModel['fields'])) {
                throw new RuntimeException('Missing configuration entry for key ' . $configKey);
            } else {
                $entityCount = 1;
            }
        } else {
            if (is_array($configuration[$configKey])) {
                $entityCount = $configuration[$configKey]['count'];
            } else {
                $entityCount = $configuration[$configKey];
            }
        }

        $entityXml = new EntityGenerator(
            Inflector::tableize($modelName),
            $entityModel,
            $entityCount,
            $relations,
            $relationList,
            $configuration['langs'],
            $parentEntities
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
