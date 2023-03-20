<?php

namespace ShopGenerator\Fixture;

use Doctrine\Inflector\Inflector;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * This basically parses all YAML configuration files to create FixtureDefinition objects
 */
class YamlFixtureConfigurationLoader
{
    private Inflector $inflector;

    public function __construct(Inflector $inflector)
    {
        $this->inflector = $inflector;
    }

    /**+
     * @return FixtureDefinition[]
     */
    public function loadDefinitions(string $directory): array
    {
        // load model data, how fixtures are generated
        $finder = new Finder();
        $definitionsFiles = $finder->files()
            ->in($directory)
            ->getIterator()
        ;

        $definitions = [];
        foreach ($definitionsFiles as $file) {
            $definition = $this->parse($file);
            $definitions[$definition->getFixtureClass()] = $definition;
        }

        return $definitions;
    }

    public function parse(SplFileInfo $filePath): FixtureDefinition
    {
        $model = Yaml::parseFile($filePath->getPathname());

        $id = $model['fields']['id'] ?? null;

        $langFields = array_key_exists('fields_lang', $model) ? $model['fields_lang']['columns'] : [];

        $imageDirectory = $model['fields']['image'] ?? null;
        $imageWidth = $model['fields']['image_width'] ?? 200;
        $imageHeight = $model['fields']['image_height'] ?? 200;
        $imageCategory = $model['fields']['image_category'] ?? null;

        return new FixtureDefinition(
            $filePath->getFilenameWithoutExtension(),
            $this->tableize($filePath->getFilenameWithoutExtension()),
            $model['fields']['columns'],
            $langFields,
            $this->extractParentEntities($model),
            $id,
            $model['fields']['class'] ?? null,
            $model['fields']['sql'] ?? null,
            $model['fields']['primary'] ?? null,
            $imageDirectory,
            $imageCategory,
            $imageWidth,
            $imageHeight,
        );
    }

    /**
     * Tableize strings in a store the result in a local cache
     */
    private function tableize($string): string
    {
        static $tableizeStrings = [];
        if (!isset($tableizeStrings[$string])) {
            $tableizeStrings[$string] = $this->inflector->tableize($string);
        }

        return $tableizeStrings[$string];
    }

    private function extractParentEntities(array $entityModel): array
    {
        $parentEntities = [];

        foreach ($entityModel['fields']['columns'] as $fieldDescription) {
            if (array_key_exists('generate_all', $fieldDescription)) {
                $relation = $fieldDescription['relation'];
                $parentEntities[] = $relation;
            }
        }

        return $parentEntities;
    }
}
