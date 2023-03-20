<?php

namespace ShopGenerator;

use Faker\Factory;
use ShopGenerator\Fixture\FixtureDefinition;
use ShopGenerator\Fixture\FixtureDefinitionCollection;
use ShopGenerator\Fixture\FixtureGenerator;
use ShopGenerator\Fixture\YamlFixtureConfigurationLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class Generator
{
    private DefaultDataParser $parser;
    private string $defaultDataDirectory;
    private string $targetDirectory;
    private YamlFixtureConfigurationLoader $yamlLoader;
    private string $modelsDirectory;
    private string $configurationFile;
    private Filesystem $fileSystem;
    private XmlEncoder $encoder;

    public function __construct(
        string $configFile,
        string $defaultDataDirectory,
        string $targetDirectory,
        string $modelsDirectory,
        YamlFixtureConfigurationLoader $modelParser,
        DefaultDataParser $parser,
    ) {
        $this->parser = $parser;
        $this->defaultDataDirectory = $defaultDataDirectory;
        $this->targetDirectory = $targetDirectory;
        $this->yamlLoader = $modelParser;
        $this->modelsDirectory = $modelsDirectory;
        $this->configurationFile = $configFile;
        $this->fileSystem = new Filesystem();
        $this->encoder = new XmlEncoder();
    }

    public function generate(): void
    {
        $configurationLoader = new ConfigurationLoader();
        $configuration = $configurationLoader->loadConfig($this->configurationFile);

        $this->fileSystem->remove($this->targetDirectory);
        $this->fileSystem->mkdir($this->targetDirectory);

        // At this point, default data files are parsed with both structure and data.
        $definitions = $this->yamlLoader->loadDefinitions($this->modelsDirectory);

        // sort dependencies
        uasort($definitions, static function (FixtureDefinition $definition): int {
            return count($definition->getRelations());
        });

        $definitionsCollection = new FixtureDefinitionCollection($definitions);

        $generator = new FixtureGenerator($configuration);
        $generator->setDefinitionCollection($definitionsCollection);

        $generator->setInitialData(
            $this->parser->loadInitialData($this->defaultDataDirectory)
        );

        foreach ($definitions as $definition) {
            $generator->generateForDefinition($definition);
        }

        foreach ($generator->getEntitiesByDefinition() as $definitionKey => $fixtures) {
            try {
                $definition = $definitionsCollection->getDefinition($definitionKey);
                $this->dumpFile($definition, array_values($fixtures), $definitionsCollection);
                $this->generateImages($definition, $fixtures);
            } catch (\RuntimeException $exception) {
                continue; // do not dump fixtures loaded through xml
            }
        }

        foreach ($generator->getEntitiesTranslations() as $definition => $translations) {
            foreach ($configuration['langs'] as $lang) {
                $this->dumpTranslationsFiles($definitions[$definition], $translations, $lang);
            }
        }
    }

    private function generateImages(FixtureDefinition $definition, array $fixtures): void
    {
        if (null === $definition->getImageDirectory()) {
            return;
        }

        $this->fileSystem->mkdir(sprintf('%s/img/%s', $this->targetDirectory, $definition->getImageDirectory()));

        $faker = Factory::create();
        $defaultTargetPath = sprintf('%s/img/%s/%s.jpg', $this->targetDirectory, $definition->getImageDirectory(), 'default');
        $filepath = $faker->image(
            dirname($defaultTargetPath),
            $definition->getImgWidth(),
            $definition->getImgHeight(),
            $definition->getImageCategory(),
            $defaultTargetPath
        );
        $this->fileSystem->rename($filepath, $defaultTargetPath);

        foreach ($fixtures as $fixtureId => $fixture) {
            $targetPath = sprintf('%s/img/%s/%s.jpg', $this->targetDirectory, $definition->getImageDirectory(), $fixtureId);
            $this->fileSystem->copy($defaultTargetPath, $targetPath);
        }
    }

    private function dumpFile(FixtureDefinition $definition, array $fixtures, FixtureDefinitionCollection $collection): void
    {
        $fieldData = [];
        foreach ($definition->getColumns() as $column => $columnDefintion) {
            $field = [
                '@name' => $column,
            ];
            if (array_key_exists('relation', $columnDefintion)) {
                $field['@relation'] = $collection->getDefinitionByModel($columnDefintion['relation'])->getFixtureClass();
            }

            $fieldData[] = $field;
        }

        $fields = [
            'field' => $fieldData,
        ];

        if ($definition->getId() !== null) {
            $fields['@id'] = $definition->getId();
        }
        if ($definition->getClass()) {
            $fields['@class'] = $definition->getClass();
        }
        if ($definition->getPrimary() !== null) {
            $fields['@primary'] = $definition->getPrimary();
        }
        if ($definition->getSql() !== null) {
            $fields['@sql'] = $definition->getSql();
        }

        $data = [
            'fields' => $fields,
            'entities' => [
                $definition->getFixtureClass() => array_values($fixtures),
            ],
        ];

        $content = $this->encoder->encode($data, 'xml', [
            'xml_root_node_name' => sprintf('entity_%s', $definition->getFixtureClass()),
            'xml_format_output' => true,
            'xml_encoding' => 'UTF-8',
        ]);

        $this->fileSystem->dumpFile(
            sprintf('%s/data/%s.xml', $this->targetDirectory, $definition->getFixtureClass()),
            $content
        );
    }

    private function dumpTranslationsFiles(FixtureDefinition $definition, array $translations, string $lang): void
    {
        $data = [];
        foreach ($translations as $fixtureId => $translation) {
            $translation[$lang]['@id'] = $fixtureId;
            $data[$definition->getFixtureClass()][] = $translation[$lang];
        }

        $content = $this->encoder->encode($data, 'xml', [
            'xml_root_node_name' => sprintf('entity_%s', $definition->getFixtureClass()),
            'xml_format_output' => true,
            'xml_encoding' => 'UTF-8',
        ]);

        $this->fileSystem->dumpFile(
            sprintf('%s/langs/%s/%s.xml',
                $this->targetDirectory,
                substr($lang, 0, 2),
                $definition->getFixtureClass()),
            $content
        );
    }
}
