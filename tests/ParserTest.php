<?php

namespace Tests;

use Doctrine\Inflector\Inflector;
use PHPUnit\Framework\TestCase;
use ShopGenerator\Fixture\YamlFixtureConfigurationLoader;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @covers \ShopGenerator\Dumper
 */
class ParserTest extends TestCase
{
    public function testParse()
    {
        $parser = new YamlFixtureConfigurationLoader($this->createMock(Inflector::class));

        $file = new SplFileInfo(sprintf("%s/samples/Profile.yml", __DIR__), '/samples/', '/samples/Profile.yml');

        $model = $parser->parse($file);

        self::assertSame('name', $model->getId());
        self::assertSame('Profile', $model->getClass());
        self::assertSame('a.id_profile > 1', $model->getSql());
    }
}
