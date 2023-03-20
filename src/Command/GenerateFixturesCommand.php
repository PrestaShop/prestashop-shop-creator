<?php

namespace ShopGenerator\Command;

use Doctrine\Inflector\InflectorFactory;
use ShopGenerator\DefaultDataParser;
use ShopGenerator\Fixture\YamlFixtureConfigurationLoader;
use ShopGenerator\Generator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'generate:fixtures')]
class GenerateFixturesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inflector = InflectorFactory::create()->build();

        $generator = new Generator(
            sprintf('%s/app/config/config.yml', dirname(__DIR__, 2)),
            dirname(__DIR__) . '/../default_data/',
            dirname(__DIR__) . '/../generated_data2',
            dirname(__DIR__) . '/../src/Model/',
            new YamlFixtureConfigurationLoader($inflector),
            new DefaultDataParser(),
        );

        $generator->generate();

        return 0;
    }
}
